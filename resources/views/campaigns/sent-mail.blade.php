<x-layout title="Sent Mail" page-title="Sent Mail" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Sent Mail']]" />

    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Every email actually delivered from this app, most recent first.</p>

    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">Campaign Sends</h3>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Sent At</th>
                        <th class="text-left px-4 py-3">Campaign</th>
                        <th class="text-left px-4 py-3">Recipient</th>
                        <th class="text-left px-4 py-3">Email</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($sent as $row)
                    <tr>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $row->sent_at?->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200">
                            <a href="{{ route('campaigns.show', $row->campaign) }}" wire:navigate class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ $row->campaign?->name ?? 'Deleted campaign' }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $row->name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $row->email }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                        <i class="ti ti-mail-off text-3xl block mb-2"></i>
                        No campaign mail sent yet.
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
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $row->created_at?->format('d M Y, H:i') }}</td>
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
</x-layout>
