<div>
    <x-breadcrumb :items="[['name' => 'Users']]" />

    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $users->total() }} user{{ $users->total() === 1 ? '' : 's' }}</p>
        <a href="{{ route('admin.users.create') }}" wire:navigate
           class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">
            <i class="ti ti-user-plus text-base"></i>
            <span>Add User</span>
        </a>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">User</th>
                        <th class="text-left px-4 py-3">Contact</th>
                        <th class="text-left px-4 py-3">Role / Section</th>
                        <th class="text-left px-4 py-3">Joined</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-800 dark:text-slate-100 truncate">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 truncate">{{ '@'.$user->username }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                            <p>{{ $user->email }}</p>
                            @if($user->mobile)<p class="text-xs text-slate-400 dark:text-slate-500">{{ $user->mobile }}</p>@endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $user->role === 'SuperAdmin' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                                {{ $user->role }}
                            </span>
                            @if($user->email_verified_at === null)
                            <span class="badge bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400">Pending activation</span>
                            @endif
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ $user->designation?->name }}{{ $user->post ? ' ('.$user->post.')' : '' }}{{ $user->section ? ' · '.$user->section->name : '' }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $user->created_at->ist()->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if(auth()->user()->isAdmin() || $user->role !== 'SuperAdmin')
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.users.edit', $user) }}" wire:navigate
                                   class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors" title="Edit">
                                    <i class="ti ti-pencil text-base"></i>
                                </a>
                                @if($user->id !== auth()->id())
                                <button type="button" wire:click="delete({{ $user->id }})"
                                        wire:confirm="Deactivate this user? They will no longer be able to sign in."
                                        class="text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="Deactivate">
                                    <i class="ti ti-trash text-base"></i>
                                </button>
                                @endif
                            </div>
                            @else
                            <span class="text-xs text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No users yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</div>
