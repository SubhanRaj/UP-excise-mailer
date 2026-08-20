<div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat-card">
            <div class="stat-icon bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                <i class="ti ti-map-2"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Zones</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $zoneCount }}</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                <i class="ti ti-building-community"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Divisions</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $divisionCount }}</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                <i class="ti ti-map-pin"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Districts</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $districtCount }}</p>
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

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-1">Recent campaigns</h2>
        <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Signed in as {{ auth()->user()->name }} &middot; {{ auth()->user()->email }} &middot; {{ auth()->user()->role }}</p>

        @if ($recentCampaigns->isEmpty())
            <div class="flex items-center gap-3 text-sm text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-900 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg px-4 py-6 justify-center">
                <i class="ti ti-inbox text-lg"></i>
                No campaigns yet — recipient import, templates, and campaign builder are next on the roadmap (see summary.md).
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide">
                        <th class="pb-2 font-semibold">Name</th>
                        <th class="pb-2 font-semibold">Mail account</th>
                        <th class="pb-2 font-semibold">Status</th>
                        <th class="pb-2 font-semibold">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($recentCampaigns as $campaign)
                        <tr>
                            <td class="py-2 text-slate-700 dark:text-slate-200">{{ $campaign->name }}</td>
                            <td class="py-2 text-slate-500 dark:text-slate-400">{{ $campaign->mailAccount?->gmail_address }}</td>
                            <td class="py-2"><span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $campaign->status }}</span></td>
                            <td class="py-2 text-slate-400 dark:text-slate-500">{{ $campaign->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
