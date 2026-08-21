<x-layout title="Sent Mail" page-title="Sent Mail" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Sent Mail']]" />

    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Every email this app has ever tried to send, across every campaign — most recent first.</p>

    @php
        $statusFilter = request('status');
        $baseQuery = fn (array $overrides = []) => request()->fullUrlWithQuery(array_merge(['page' => null], $overrides));
        $statCardClass = fn (?string $value) => 'stat-card block text-left transition-shadow'
            .($statusFilter === $value ? ' ring-2 ring-indigo-500' : '');
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <a href="{{ $baseQuery(['status' => 'pending']) }}" class="{{ $statCardClass('pending') }}">
            <div class="stat-icon bg-slate-50 dark:bg-slate-900/30 text-slate-500 dark:text-slate-400"><i class="ti ti-clock"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Waiting</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['pending'] ?? 0 }}</p>
            </div>
        </a>
        <a href="{{ $baseQuery(['status' => 'queued']) }}" class="{{ $statCardClass('queued') }}">
            <div class="stat-icon bg-sky-50 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400"><i class="ti ti-loader"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Queued</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['queued'] ?? 0 }}</p>
            </div>
        </a>
        <a href="{{ $baseQuery(['status' => 'sent']) }}" class="{{ $statCardClass('sent') }}">
            <div class="stat-icon bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400"><i class="ti ti-circle-check"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Sent</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['sent'] ?? 0 }}</p>
            </div>
        </a>
        <a href="{{ $baseQuery(['status' => 'failed']) }}" class="{{ $statCardClass('failed') }}">
            <div class="stat-icon bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400"><i class="ti ti-alert-circle"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Failed</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['failed'] ?? 0 }}</p>
            </div>
        </a>
        <a href="{{ $baseQuery(['status' => null]) }}" class="{{ $statCardClass(null) }}">
            <div class="stat-icon bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400"><i class="ti ti-mail"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Total</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ array_sum($statusCounts->all()) }}</p>
            </div>
        </a>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        @if($sort !== 'activity')<input type="hidden" name="sort" value="{{ $sort }}">@endif
        @if($direction !== 'desc')<input type="hidden" name="direction" value="{{ $direction }}">@endif
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email…"
               x-data x-on:input.debounce.500ms="$el.form.requestSubmit()"
               class="flex-1 min-w-[180px] text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2">
        <select name="status" x-data x-on:change="$el.form.requestSubmit()"
                class="text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2">
            <option value="">All statuses</option>
            <option value="pending" @selected($statusFilter === 'pending')>Waiting</option>
            <option value="queued" @selected($statusFilter === 'queued')>Queued</option>
            <option value="sent" @selected($statusFilter === 'sent')>Sent</option>
            <option value="failed" @selected($statusFilter === 'failed')>Failed</option>
        </select>
        <noscript><button type="submit" class="text-sm font-medium px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">Filter</button></noscript>
        @if(request('q') || $statusFilter)
        <a href="{{ route('campaigns.sent-mail') }}" class="text-sm text-slate-500 hover:underline">Clear</a>
        @endif
    </form>

    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">Campaign Sends</h3>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        @foreach(['activity' => 'Sent / Failed At', 'name' => 'Recipient', 'email' => 'Email', 'status' => 'Status'] as $field => $label)
                        @php
                            $nextDirection = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
                            $icon = $sort !== $field ? 'ti-selector' : ($direction === 'asc' ? 'ti-sort-ascending' : 'ti-sort-descending');
                        @endphp
                        <th class="text-left px-4 py-3">
                            <a href="{{ $baseQuery(['sort' => $field, 'direction' => $nextDirection]) }}"
                               class="inline-flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $label }} <i class="ti {{ $icon }} text-sm"></i>
                            </a>
                        </th>
                        @endforeach
                        <th class="text-left px-4 py-3">Campaign</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($sent as $row)
                    @php
                        $statusColor = match($row->status) {
                            'sent' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400',
                            'failed' => 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400',
                            'queued' => 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-400',
                            default => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
                        };
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            @if($row->sent_at)
                                {{ $row->sent_at->ist()->format('d M Y, H:i') }}
                            @elseif($row->failed_at)
                                <span class="text-red-400 dark:text-red-500">Failed {{ $row->failed_at->ist()->format('d M Y, H:i') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $row->name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $row->email }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $statusColor }} capitalize">{{ $row->status === 'pending' ? 'Waiting' : $row->status }}</span>
                            @if($row->status === 'failed' && $row->error_message)
                            <p class="text-xs text-red-500 dark:text-red-400 mt-1">{{ $row->error_message }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200">
                            <a href="{{ route('campaigns.show', $row->campaign) }}" wire:navigate class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ $row->campaign?->name ?? 'Deleted campaign' }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                        <i class="ti ti-mail-off text-3xl block mb-2"></i>
                        No campaign mail matches this filter.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 mb-6">{{ $sent->links() }}</div>

    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">Test Sends</h3>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Sent At</th>
                        <th class="text-left px-4 py-3">To</th>
                        <th class="text-left px-4 py-3">Via</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">Sent By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($testSends as $row)
                    @php
                        $status = $row->metadata['status'] ?? 'sent';
                        $statusColor = $status === 'failed'
                            ? 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400'
                            : 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400';
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $row->created_at?->ist()->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $row->metadata['to'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $row->metadata['via'] ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $statusColor }} capitalize">{{ $status }}</span>
                            @if($status === 'failed' && ($row->metadata['error'] ?? null))
                            <p class="text-xs text-red-500 dark:text-red-400 mt-1">{{ $row->metadata['error'] }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $row->user?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No test emails sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // See campaigns/show.blade.php for the same pattern — toasts what changed since last
        // load (deferred to DOMContentLoaded) and auto-refreshes while anything's still
        // pending/queued.
        document.addEventListener('DOMContentLoaded', function () {
            const key = 'sentMailRecipientStatus';
            const current = @json($sent->getCollection()->mapWithKeys(fn ($r) => [$r->id => ['email' => $r->email, 'status' => $r->status]]));
            const previous = JSON.parse(sessionStorage.getItem(key) || 'null');
            sessionStorage.setItem(key, JSON.stringify(current));

            if (previous) {
                const sent = [], failed = [];
                for (const [id, row] of Object.entries(current)) {
                    const before = previous[id];
                    if (! before || before.status === row.status) continue;
                    if (row.status === 'sent') sent.push(row.email);
                    if (row.status === 'failed') failed.push(row.email);
                }
                if (sent.length || failed.length) {
                    const describe = (list, label) => list.length
                        ? label + ': ' + list.slice(0, 3).join(', ') + (list.length > 3 ? ` (+${list.length - 3} more)` : '')
                        : '';
                    const message = [describe(sent, 'Sent'), describe(failed, 'Failed')].filter(Boolean).join(' — ');
                    flashToast(failed.length && ! sent.length ? 'error' : 'success', message);
                }
            }
        });

        // See campaigns/show.blade.php for why this polls instead of loading its own script tag.
        function flashToast(type, message, attempt = 0) {
            if (window.flasher) { window.flasher[type](message); return; }
            if (attempt > 40) return;
            setTimeout(() => flashToast(type, message, attempt + 1), 100);
        }

        @if($sent->contains(fn ($r) => in_array($r->status, ['pending', 'queued'], true)))
        setInterval(() => {
            const active = document.activeElement?.tagName;
            if (active === 'INPUT' || active === 'SELECT' || active === 'TEXTAREA') return;
            window.location.reload();
        }, 6000);
        @endif
    </script>
</x-layout>
