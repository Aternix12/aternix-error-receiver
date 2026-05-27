<?php

namespace App\Http\Controllers;

use App\Models\ErrorReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ErrorReportController extends Controller
{
    /**
     * Receive an error report from any Aternix app.
     */
    public function store(Request $request)
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'project' => 'nullable|string|max:100',
            'app_version' => 'nullable|string|max:50',
            'platform' => 'nullable|string|max:255',
            'hostname' => 'nullable|string|max:255',
            'report_type' => 'nullable|string|in:auto,manual',
            'summary' => 'nullable|string|max:500',
            'user_note' => 'nullable|string|max:5000',
            'frontend_report' => 'nullable|string|max:100000',
            'log_tail' => 'nullable|string|max:500000',
        ]);

        $report = ErrorReport::create([
            ...$validated,
            'project' => $validated['project'] ?? 'unknown',
            'report_type' => $validated['report_type'] ?? 'auto',
            'client_ip' => $request->ip(),
        ]);

        $this->notify($report);

        return response()->json([
            'success' => true,
            'data' => ['id' => $report->id],
            'message' => 'Report received',
        ], 201);
    }

    /**
     * List the most recent reports (token-guarded). Optionally filter by ?project=.
     */
    public function index(Request $request)
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $reports = ErrorReport::query()
            ->when($request->query('project'), fn ($q, $project) => $q->where('project', $project))
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'data' => $reports]);
    }

    private function tokenIsValid(Request $request): bool
    {
        $expected = config('error_reports.token');

        // No token configured => accept everything (e.g. local dev).
        if (empty($expected)) {
            return true;
        }

        return hash_equals((string) $expected, (string) $request->header('X-Report-Token'));
    }

    private function notify(ErrorReport $report): void
    {
        $to = config('error_reports.notify_email');
        if (empty($to)) {
            return;
        }

        try {
            $body = "A new error report was received.\n\n"
                ."ID:        {$report->id}\n"
                ."Project:   {$report->project}\n"
                ."Type:      {$report->report_type}\n"
                ."Version:   {$report->app_version}\n"
                ."Platform:  {$report->platform}\n"
                ."Computer:  {$report->hostname}\n"
                ."Summary:   {$report->summary}\n\n"
                ."--- What happened ---\n{$report->frontend_report}\n\n"
                ."--- Log tail ---\n{$report->log_tail}\n";

            Mail::raw($body, function ($message) use ($to, $report) {
                $message->to($to)->subject("[{$report->project}] Error Report #{$report->id} — {$report->summary}");
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to email error report #'.$report->id.': '.$e->getMessage());
        }
    }
}
