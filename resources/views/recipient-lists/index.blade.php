<x-layout title="Recipient Lists" page-title="Recipient Lists" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Recipient Lists']]" />

    <div class="flex items-center justify-between mb-4 border-b border-slate-200 dark:border-slate-700">
        <div class="flex items-center gap-1">
            @foreach(['zones' => 'Zones', 'divisions' => 'Divisions', 'districts' => 'Districts'] as $key => $label)
            <a href="{{ route('recipients.index', ['tab' => $key]) }}" wire:navigate
               class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                {{ $label }}
            </a>
            @endforeach
            <span class="px-4 py-2 text-sm font-medium border-b-2 -mb-px border-indigo-600 text-indigo-600 dark:text-indigo-400">
                Ad-hoc Lists
            </span>
        </div>
    </div>

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $lists->total() }} list{{ $lists->total() === 1 ? '' : 's' }}</p>
        @if(auth()->user()->hasPrivilege('recipients.import'))
        <div class="flex items-center gap-2">
            <a href="{{ route('recipient-lists.create', ['mode' => 'manual']) }}" wire:navigate
               class="inline-flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
                <i class="ti ti-edit text-base"></i>
                <span>Add Recipients Manually</span>
            </a>
            <a href="{{ route('recipient-lists.create') }}" wire:navigate
               class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
                <i class="ti ti-upload text-base"></i>
                <span>Import File</span>
            </a>
        </div>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Name</th>
                        <th class="text-left px-4 py-3">Source</th>
                        <th class="text-left px-4 py-3">Recipients</th>
                        <th class="text-left px-4 py-3">Uploaded By</th>
                        <th class="text-left px-4 py-3">Date</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($lists as $list)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">
                            <a href="{{ route('recipient-lists.show', $list) }}" wire:navigate class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ $list->name }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">{{ $list->source_type }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $list->items_count }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $list->uploadedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $list->created_at->ist()->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if(auth()->user()->hasPrivilege('recipients.import'))
                            <form method="POST" action="{{ route('recipient-lists.destroy', $list) }}"
                                  data-confirm="Delete this recipient list?" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="Delete">
                                    <i class="ti ti-trash text-base"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No recipient lists imported yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $lists->links() }}</div>
</x-layout>
