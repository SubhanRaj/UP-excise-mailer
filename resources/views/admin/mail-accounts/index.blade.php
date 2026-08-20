<x-layout title="Mail Accounts" page-title="Mail Accounts" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Mail Accounts']]" />

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $mailAccounts->count() }} mail account{{ $mailAccounts->count() === 1 ? '' : 's' }}</p>
        <a href="{{ route('admin.mail-accounts.create') }}" wire:navigate
           class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
            <i class="ti ti-brand-gmail text-base"></i>
            <span>Add Mail Account</span>
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Address</th>
                        <th class="text-left px-4 py-3">Section</th>
                        <th class="text-left px-4 py-3">Delay Between Sends</th>
                        <th class="text-left px-4 py-3">Daily Cap</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($mailAccounts as $account)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $account->gmail_address }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $account->section->name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $account->throttle_seconds }}s</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $account->daily_send_cap ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $account->is_active ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400' }}">
                                {{ $account->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.mail-accounts.edit', $account) }}" wire:navigate
                                   class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors" title="Edit">
                                    <i class="ti ti-pencil text-base"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.mail-accounts.destroy', $account) }}"
                                      onsubmit="return confirm('Delete this mail account?');">
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
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No mail accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
