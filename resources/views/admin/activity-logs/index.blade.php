<x-layout title="Activity Log" page-title="Activity Log" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Activity Log']]" />

    <form method="POST" action="{{ route('admin.activity.filters') }}" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 mb-4 flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label class="field-label">User</label>
            <select name="user_id" class="field-input">
                <option value="">All users</option>
                @foreach($users as $user)
                <option value="{{ $user->id }}" {{ ($filters['user_id'] ?? null) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="field-label">Action</label>
            <select name="action" class="field-input">
                <option value="">All actions</option>
                @foreach($actions as $action)
                <option value="{{ $action }}" {{ ($filters['action'] ?? null) === $action ? 'selected' : '' }}>{{ \App\Http\Controllers\Admin\ActivityLogController::labelFor($action) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="field-label">IP Address</label>
            <input type="text" name="ip" value="{{ $filters['ip'] ?? '' }}" class="field-input">
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">Filter</button>
            @if(!empty($filters))
            <button type="submit" onclick="this.form.querySelectorAll('select,input[type=text]').forEach(el => el.value = '')" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 px-2">Clear</button>
            @endif
        </div>
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Timestamp</th>
                        <th class="text-left px-4 py-3">User</th>
                        <th class="text-left px-4 py-3">Action</th>
                        <th class="text-left px-4 py-3">IP Address</th>
                        <th class="text-left px-4 py-3">Path</th>
                        <th class="text-left px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($logs as $log)
                    @php
                        $status = $log->metadata['status'] ?? null;
                        $statusColor = match(true) {
                            $status >= 500 => 'text-red-600 dark:text-red-400',
                            $status >= 400 => 'text-amber-600 dark:text-amber-400',
                            $status >= 300 => 'text-sky-600 dark:text-sky-400',
                            $status >= 200 => 'text-emerald-600 dark:text-emerald-400',
                            default => 'text-slate-500 dark:text-slate-400',
                        };
                        $path = isset($log->metadata['url']) ? parse_url($log->metadata['url'], PHP_URL_PATH) : null;
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            <p>{{ $log->created_at->format('d M Y') }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ $log->created_at->format('H:i:s') }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                            {{ $log->user?->name ?? 'Deleted user' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                {{ \App\Http\Controllers\Admin\ActivityLogController::labelFor($log->action) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded">{{ $log->ip_address }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 truncate max-w-xs">{{ $path }}</td>
                        <td class="px-4 py-3 font-medium {{ $statusColor }}">{{ $status }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-layout>
