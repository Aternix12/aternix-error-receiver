<!DOCTYPE html>
<html lang="en" class="bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aternix Error Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="text-slate-800">
    <div class="max-w-7xl mx-auto p-6">
        <header class="flex items-center justify-between mb-6 flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Error Reports</h1>
                <p class="text-sm text-slate-500">Aternix Error Receiver — errors.aternix.com</p>
            </div>
            <form method="get" action="/dashboard" class="flex gap-2 items-center flex-wrap">
                <select name="project" class="border border-slate-300 rounded px-3 py-2 text-sm bg-white">
                    <option value="">All projects</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->project }}" @selected($filter['project'] === $p->project)>{{ $p->project }} ({{ $p->total }})</option>
                    @endforeach
                </select>
                <select name="type" class="border border-slate-300 rounded px-3 py-2 text-sm bg-white">
                    <option value="">All types</option>
                    <option value="auto" @selected($filter['type'] === 'auto')>Auto</option>
                    <option value="manual" @selected($filter['type'] === 'manual')>Manual</option>
                </select>
                <input type="text" name="q" value="{{ $filter['search'] }}" placeholder="Search summary / log / note…" class="border border-slate-300 rounded px-3 py-2 text-sm w-64 bg-white">
                <button class="bg-slate-800 text-white rounded px-4 py-2 text-sm hover:bg-slate-700">Filter</button>
                @if($filter['project'] || $filter['type'] || $filter['search'])
                    <a href="/dashboard" class="text-sm text-slate-500 hover:underline">Reset</a>
                @endif
            </form>
        </header>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="bg-white rounded-lg p-4 border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wide">Total</div>
                <div class="text-2xl font-semibold mt-1">{{ number_format($totals['all']) }}</div>
            </div>
            <div class="bg-white rounded-lg p-4 border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wide">Last 24h</div>
                <div class="text-2xl font-semibold mt-1">{{ number_format($totals['last_24h']) }}</div>
            </div>
            <div class="bg-white rounded-lg p-4 border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wide">Auto-sent</div>
                <div class="text-2xl font-semibold mt-1">{{ number_format($totals['auto']) }}</div>
            </div>
            <div class="bg-white rounded-lg p-4 border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wide">Manual</div>
                <div class="text-2xl font-semibold mt-1">{{ number_format($totals['manual']) }}</div>
            </div>
        </div>

        @if($projects->count() && !$filter['project'])
            <div class="bg-white rounded-lg border border-slate-200 mb-6 overflow-hidden">
                <div class="px-4 py-2 border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">Projects</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-200">
                    @foreach($projects as $p)
                        <a href="/dashboard?project={{ urlencode($p->project) }}" class="px-4 py-3 hover:bg-slate-50 flex justify-between items-baseline">
                            <span class="font-medium">{{ $p->project }}</span>
                            <span class="text-sm text-slate-500">{{ $p->total }} · {{ \Carbon\Carbon::parse($p->last_seen)->diffForHumans() }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="space-y-2">
            @forelse($reports as $r)
                <details class="bg-white rounded-lg border border-slate-200 group">
                    <summary class="px-4 py-3 cursor-pointer flex justify-between items-center hover:bg-slate-50 gap-3">
                        <div class="flex gap-3 items-center min-w-0">
                            <span class="text-xs text-slate-500 whitespace-nowrap" title="{{ $r->created_at }}">{{ $r->created_at->diffForHumans() }}</span>
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-xs whitespace-nowrap">{{ $r->project }}</span>
                            <span class="px-2 py-0.5 rounded text-xs whitespace-nowrap {{ $r->report_type === 'manual' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700' }}">{{ $r->report_type }}</span>
                            <span class="truncate font-medium">{{ $r->summary ?: '—' }}</span>
                        </div>
                        <span class="text-slate-400 group-open:rotate-180 transition-transform shrink-0">▾</span>
                    </summary>
                    <div class="border-t border-slate-200 p-4 space-y-4 text-sm">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                            <div><div class="text-slate-400">ID</div><div class="font-mono">{{ $r->id }}</div></div>
                            <div><div class="text-slate-400">Version</div><div>{{ $r->app_version ?: '—' }}</div></div>
                            <div><div class="text-slate-400">Platform</div><div>{{ $r->platform ?: '—' }}</div></div>
                            <div><div class="text-slate-400">Computer</div><div>{{ $r->hostname ?: '—' }}</div></div>
                        </div>
                        @if($r->user_note)
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">User note</div>
                                <div class="bg-amber-50 border border-amber-200 rounded p-3 whitespace-pre-wrap">{{ $r->user_note }}</div>
                            </div>
                        @endif
                        @if($r->frontend_report)
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">What happened (frontend)</div>
                                <pre class="bg-slate-50 border border-slate-200 rounded p-3 text-xs overflow-x-auto whitespace-pre-wrap">{{ $r->frontend_report }}</pre>
                            </div>
                        @endif
                        @if($r->log_tail)
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">Log tail</div>
                                <pre class="bg-slate-900 text-slate-200 rounded p-3 text-xs overflow-x-auto whitespace-pre-wrap max-h-96">{{ $r->log_tail }}</pre>
                            </div>
                        @endif
                    </div>
                </details>
            @empty
                <div class="bg-white rounded-lg border border-slate-200 px-4 py-12 text-center text-slate-400">No reports match.</div>
            @endforelse
        </div>

        <div class="mt-6">{{ $reports->links() }}</div>
    </div>
</body>
</html>
