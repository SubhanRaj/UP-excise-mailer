<x-layout title="Sent Mail" page-title="Sent Mail" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Campaigns', 'url' => route('campaigns.index')], ['name' => 'Sent Mail']]" />

    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
        Every campaign email actually delivered, most recent first. Test emails (sent via "Send Test Email") show up in
        <a href="{{ route('admin.activity.index') }}" wire:navigate class="text-indigo-600 dark:text-indigo-400 hover:underline">Activity Log</a> instead.
    </p>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
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
                        No mail sent yet.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $sent->links() }}</div>
</x-layout>
