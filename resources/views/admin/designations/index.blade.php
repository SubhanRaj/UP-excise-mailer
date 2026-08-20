<x-layout title="Designations" page-title="Designations" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Designations']]" />

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $designations->count() }} designation{{ $designations->count() === 1 ? '' : 's' }}</p>
        <a href="{{ route('admin.designations.create') }}" wire:navigate
           class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
            <i class="ti ti-id-badge-2 text-base"></i>
            <span>Add Designation</span>
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Name</th>
                        <th class="text-left px-4 py-3">Default Privileges</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($designations as $designation)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $designation->name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ count($designation->default_privileges ?? []) }} selected</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.designations.edit', $designation) }}" wire:navigate
                                   class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors" title="Edit">
                                    <i class="ti ti-pencil text-base"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.designations.destroy', $designation) }}"
                                      onsubmit="return confirm('Delete this designation? Existing users keep whatever privileges they already have.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="Delete">
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No designations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
