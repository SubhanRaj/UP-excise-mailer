<x-layout title="{{ $list->name }}" page-title="{{ $list->name }}" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Recipient Lists', 'url' => route('recipient-lists.index')], ['name' => $list->name]]" />

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Name</th>
                        <th class="text-left px-4 py-3">Email</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($items as $item)
                    <tr>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $item->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $item->email }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No recipients in this list.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</x-layout>
