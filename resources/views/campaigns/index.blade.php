<x-layout title="Campaigns" page-title="Campaigns" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Campaigns']]" />

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $campaigns->total() }} campaign{{ $campaigns->total() === 1 ? '' : 's' }}</p>
        <div class="flex items-center gap-2">
            @if(auth()->user()->hasPrivilege('test-email.send'))
            <a href="{{ route('campaigns.test-send') }}" wire:navigate
               class="inline-flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
                <i class="ti ti-flask text-base"></i>
                <span>Send Test Email</span>
            </a>
            @endif
            @if(auth()->user()->hasPrivilege('campaigns.send'))
            <a href="{{ route('campaigns.create') }}" wire:navigate
               class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
                <i class="ti ti-send text-base"></i>
                <span>New Campaign</span>
            </a>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Name</th>
                        <th class="text-left px-4 py-3">Mail Account</th>
                        <th class="text-left px-4 py-3">Scope</th>
                        <th class="text-left px-4 py-3">Recipients</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($campaigns as $campaign)
                    @php
                        $scopeLabel = match($campaign->recipient_scope) {
                            'zones' => 'Zones', 'divisions' => 'Divisions', 'districts' => 'Districts',
                            'recipient_list' => 'Imported List', 'manual' => 'Typed Emails', default => 'Everyone',
                        };
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
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">
                            <a href="{{ route('campaigns.show', $campaign) }}" wire:navigate class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ $campaign->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $campaign->mailAccount->gmail_address }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $scopeLabel }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $campaign->recipients_count }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $statusColor }}">
                                @if(in_array($campaign->status, ['sending', 'queued']))
                                    Sent {{ $campaign->sent_count }} of {{ $campaign->recipients_count }}
                                @else
                                    {{ $statusLabel }}
                                @endif
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $campaign->created_at->format('d M Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                        <i class="ti ti-send text-3xl block mb-2"></i>
                        No campaigns sent yet.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $campaigns->links() }}</div>
</x-layout>
