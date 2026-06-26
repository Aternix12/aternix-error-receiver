<?php

namespace App\Http\Controllers;

use App\Models\ErrorReport;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->query('project') ?: null;
        $search = trim((string) $request->query('q', ''));
        $type = $request->query('type') ?: null;

        $reports = ErrorReport::query()
            ->when($project, fn ($q) => $q->where('project', $project))
            ->when($type, fn ($q) => $q->where('report_type', $type))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('summary', 'like', "%{$search}%")
                    ->orWhere('frontend_report', 'like', "%{$search}%")
                    ->orWhere('user_note', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $projects = ErrorReport::query()
            ->selectRaw('project, COUNT(*) as total, MAX(created_at) as last_seen')
            ->groupBy('project')
            ->orderByDesc('total')
            ->get();

        $totals = [
            'all' => ErrorReport::count(),
            'auto' => ErrorReport::where('report_type', 'auto')->count(),
            'manual' => ErrorReport::where('report_type', 'manual')->count(),
            'last_24h' => ErrorReport::where('created_at', '>=', now()->subDay())->count(),
        ];

        return view('dashboard', [
            'reports' => $reports,
            'projects' => $projects,
            'totals' => $totals,
            'filter' => compact('project', 'search', 'type'),
        ]);
    }
}
