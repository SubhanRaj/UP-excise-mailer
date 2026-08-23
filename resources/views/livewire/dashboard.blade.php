<div>
    <div class="flex flex-wrap items-center justify-end gap-2 mb-4">
        @if(auth()->user()->hasPrivilege('campaigns.send'))
        <a href="{{ route('campaigns.create') }}" wire:navigate
           class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
            <i class="ti ti-send text-base"></i>
            <span>New Campaign</span>
        </a>
        @endif
        @if(auth()->user()->hasPrivilege('recipients.import'))
        <a href="{{ route('recipient-lists.create') }}" wire:navigate
           class="inline-flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
            <i class="ti ti-upload text-base"></i>
            <span>Import Recipients</span>
        </a>
        @endif
        @if(auth()->user()->hasPrivilege('templates.manage'))
        <a href="{{ route('templates.create') }}" wire:navigate
           class="inline-flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
            <i class="ti ti-file-plus text-base"></i>
            <span>New Template</span>
        </a>
        @endif
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <a href="{{ route('recipients.index', ['tab' => 'zones']) }}" wire:navigate class="stat-card block transition-shadow hover:shadow-md">
            <div class="stat-icon bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                <i class="ti ti-map-2"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Zones</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $zoneCount }}</p>
            </div>
        </a>
        <a href="{{ route('recipients.index', ['tab' => 'divisions']) }}" wire:navigate class="stat-card block transition-shadow hover:shadow-md">
            <div class="stat-icon bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                <i class="ti ti-building-community"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Divisions</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $divisionCount }}</p>
            </div>
        </a>
        <a href="{{ route('recipients.index', ['tab' => 'districts']) }}" wire:navigate class="stat-card block transition-shadow hover:shadow-md">
            <div class="stat-icon bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                <i class="ti ti-map-pin"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Districts</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $districtCount }}</p>
            </div>
        </a>
        <a href="{{ route('recipient-lists.index') }}" wire:navigate class="stat-card block transition-shadow hover:shadow-md">
            <div class="stat-icon bg-cyan-50 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400">
                <i class="ti ti-list-details"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Recipient Lists</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $recipientListCount }}</p>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="stat-card">
            <div class="stat-icon bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                <i class="ti ti-circle-check"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Total Sent</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $totalSentCount }}</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400">
                <i class="ti ti-alert-circle"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Failed Sends</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $totalFailedCount }}</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400">
                <i class="ti ti-checkbox"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Responded</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">
                    {{ $totalRespondedCount }}
                    <span class="text-sm font-normal text-slate-400 dark:text-slate-500">{{ $responseRate === null ? '' : '('.$responseRate.'%)' }}</span>
                </p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400">
                <i class="ti ti-writing"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Mail Templates</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $templateCount }}</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400">
                <i class="ti ti-brand-gmail"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Active Mail Accounts</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $mailAccountCount }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 mb-6">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-4">Emails sent — last 14 days</h2>
        <div class="h-56">
            <canvas id="sendVolumeChart"></canvas>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-1">Recent campaigns</h2>
        <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Signed in as {{ auth()->user()->name }} &middot; {{ auth()->user()->email }} &middot; {{ auth()->user()->role }}</p>

        @if ($recentCampaigns->isEmpty())
            <div class="flex items-center gap-3 text-sm text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-900 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg px-4 py-6 justify-center">
                <i class="ti ti-inbox text-lg"></i>
                No campaigns sent yet.
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide">
                        <th class="pb-2 font-semibold">Name</th>
                        <th class="pb-2 font-semibold">Mail account</th>
                        <th class="pb-2 font-semibold">Status</th>
                        <th class="pb-2 font-semibold">Responded</th>
                        <th class="pb-2 font-semibold">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($recentCampaigns as $campaign)
                        @php
                            $statusLabel = match($campaign->status) {
                                'completed' => 'Sent', 'failed' => 'Failed', 'sending', 'queued' => 'Sending', default => 'Draft',
                            };
                            $statusColor = match($campaign->status) {
                                'completed' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400',
                                'failed' => 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400',
                                'sending', 'queued' => 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-400',
                                default => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
                            };
                        @endphp
                        <tr>
                            <td class="py-2 font-medium">
                                <a href="{{ route('campaigns.show', $campaign) }}" wire:navigate class="text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400">{{ $campaign->name }}</a>
                            </td>
                            <td class="py-2 text-slate-500 dark:text-slate-400">{{ $campaign->mailAccount?->gmail_address }}</td>
                            <td class="py-2">
                                <span class="badge {{ $statusColor }}">
                                    @if(in_array($campaign->status, ['sending', 'queued']))
                                        Sent {{ $campaign->sent_count }} of {{ $campaign->recipients_count }}
                                    @else
                                        {{ $statusLabel }}
                                    @endif
                                </span>
                            </td>
                            <td class="py-2 text-slate-500 dark:text-slate-400">
                                @if($campaign->sent_count > 0)
                                    <a href="{{ route('campaigns.show', $campaign) }}?responded=yes" wire:navigate class="hover:text-teal-600 dark:hover:text-teal-400">
                                        {{ $campaign->responded_count }}/{{ $campaign->sent_count }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 text-slate-400 dark:text-slate-500">{{ $campaign->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4 text-right">
                <a href="{{ route('campaigns.index') }}" wire:navigate class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">View all campaigns &rarr;</a>
            </div>
        @endif
    </div>

    <script>
        (function () {
            const canvas = document.getElementById('sendVolumeChart');
            if (! canvas) return;

            // wire:navigate clones/re-runs this <script> tag on every SPA visit, but a
            // dynamically inserted <script src> loads asynchronously (unlike one parsed from
            // raw HTML on a hard reload) — so `Chart` can still be undefined here on a fresh
            // navigation even though this same code works after a hard reload. Same race as
            // templates/_editor.blade.php's Quill load — load it ourselves and wait.
            if (typeof Chart === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4';
                script.onload = initChart;
                document.head.appendChild(script);
                return;
            }

            initChart();

            function initChart() {
                // wire:navigate re-runs this block but never destroys a chart bound to a
                // canvas that got wholesale-replaced with it — recreating on an already-charted
                // canvas throws "Canvas is already in use", so tear down first.
                const existing = Chart.getChart(canvas);
                if (existing) existing.destroy();

                const isDark = document.documentElement.classList.contains('dark');
                const gridColor = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(100, 116, 139, 0.1)';
                const tickColor = isDark ? '#94a3b8' : '#64748b';

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: @json($sendVolume['labels']),
                        datasets: [{
                            label: 'Emails sent',
                            data: @json($sendVolume['data']),
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: tickColor } },
                            y: { beginAtZero: true, ticks: { color: tickColor, precision: 0 }, grid: { color: gridColor } },
                        },
                    },
                });
            }
        })();
    </script>
</div>
