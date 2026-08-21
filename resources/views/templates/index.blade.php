<x-layout title="Templates" page-title="Templates" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Templates']]" />

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $templates->total() }} template{{ $templates->total() === 1 ? '' : 's' }}</p>
        @if(auth()->user()->hasPrivilege('templates.manage'))
        <a href="{{ route('templates.create') }}" wire:navigate
           class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
            <i class="ti ti-template text-base"></i>
            <span>Add Template</span>
        </a>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Name</th>
                        <th class="text-left px-4 py-3">Subject</th>
                        <th class="text-left px-4 py-3">Variables</th>
                        <th class="text-left px-4 py-3">Created</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($templates as $template)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $template->name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 truncate max-w-xs">{{ $template->subject }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            @foreach($template->variables ?? [] as $var)
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ \App\Models\MailTemplate::variableToken($var) }}</span>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $template->created_at->ist()->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if(auth()->user()->hasPrivilege('templates.manage'))
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('templates.edit', $template) }}" wire:navigate
                                   class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors" title="Edit">
                                    <i class="ti ti-pencil text-base"></i>
                                </a>
                                <form method="POST" action="{{ route('templates.destroy', $template) }}"
                                      data-confirm="Delete this template?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="Delete">
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </form>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No templates yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $templates->links() }}</div>
</x-layout>
