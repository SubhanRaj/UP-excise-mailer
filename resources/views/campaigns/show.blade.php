<x-layout title="{{ $campaign->name }}" page-title="{{ $campaign->name }}" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Campaigns', 'url' => route('campaigns.index')], ['name' => $campaign->name]]" />

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="stat-card">
            <div class="stat-icon bg-slate-50 dark:bg-slate-900/30 text-slate-500 dark:text-slate-400"><i class="ti ti-clock"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Waiting</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['pending'] ?? 0 }}</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400"><i class="ti ti-circle-check"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Sent</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['sent'] ?? 0 }}</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400"><i class="ti ti-alert-circle"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Failed</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['failed'] ?? 0 }}</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400"><i class="ti ti-users"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Total</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $recipients->total() }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Recipient</th>
                        <th class="text-left px-4 py-3">Email</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">Sent At</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($recipients as $recipient)
                    @php
                        $statusColor = match($recipient->status) {
                            'sent' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400',
                            'failed' => 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400',
                            'queued' => 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-400',
                            default => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
                        };
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $recipient->name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $recipient->email }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $statusColor }} capitalize">{{ $recipient->status === 'pending' ? 'Waiting' : $recipient->status }}</span>
                            @if($recipient->status === 'failed' && $recipient->error_message)
                            <p class="text-xs text-red-500 dark:text-red-400 mt-1">{{ $recipient->error_message }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-400 dark:text-slate-500">{{ $recipient->sent_at?->format('d M Y, H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($recipient->status === 'failed' && auth()->user()->hasPrivilege('campaigns.send'))
                            <form method="POST" action="{{ route('campaigns.retry-recipient', [$campaign, $recipient]) }}">
                                @csrf
                                <button type="submit" class="text-indigo-600 dark:text-indigo-400 hover:underline">Retry</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No recipients.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $recipients->links() }}</div>
</x-layout>
