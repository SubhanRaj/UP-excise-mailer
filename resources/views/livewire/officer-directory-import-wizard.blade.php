<div class="max-w-4xl">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">

        @if($step === 1)
        <div class="space-y-5">
            <div>
                <label class="field-label">Level</label>
                <div class="grid grid-cols-3 gap-2 mt-1">
                    <button type="button" wire:click="$set('level', 'zone')" class="px-3 py-2 rounded-lg text-sm border {{ $level === 'zone' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Zones (JEC)</button>
                    <button type="button" wire:click="$set('level', 'division')" class="px-3 py-2 rounded-lg text-sm border {{ $level === 'division' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Divisions (DEC)</button>
                    <button type="button" wire:click="$set('level', 'district')" class="px-3 py-2 rounded-lg text-sm border {{ $level === 'district' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Districts (DEO)</button>
                </div>
            </div>

            <div class="flex items-start gap-2 text-sm text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-3">
                <i class="ti ti-download flex-shrink-0 mt-0.5"></i>
                <span>
                    Download the current list first — it's already filled in with every {{ $level }} name and whatever officer details are on file, so you only need to edit what changed.
                    <a href="{{ route('recipients.template', $level) }}" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">Download {{ ucfirst($level) }} Template (.xlsx)</a>
                </span>
            </div>

            <div>
                <label class="field-label">Upload the Completed File</label>
                <input type="file" wire:model="file" accept=".xlsx,.csv" class="field-input @error('file') field-error @enderror">
                <p class="field-hint">Same columns as the download: Name, Officer Name, Officer Email, Officer CUG. Leave a cell blank to keep the current value.</p>
                @error('file')<p class="field-err-msg">{{ $message }}</p>@enderror
                <div wire:loading wire:target="upload,file" class="text-xs text-slate-400 mt-1">Reading file…</div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="button" wire:click="upload" wire:loading.attr="disabled" wire:target="upload"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Preview Changes
                </button>
                <a href="{{ route('recipients.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </div>
        @endif

        @if($step === 2)
        <div class="space-y-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ count($preview) }} row(s) read.
                {{ collect($preview)->where('matched', true)->count() }} matched an existing {{ $level }} and will be updated;
                {{ collect($preview)->where('matched', false)->count() }} didn't match any name and will be skipped.
            </p>

            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="overflow-x-auto max-h-96">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                            <tr>
                                <th class="text-left px-3 py-2">Name</th>
                                <th class="text-left px-3 py-2">Officer</th>
                                <th class="text-left px-3 py-2">Email</th>
                                <th class="text-left px-3 py-2">CUG</th>
                                <th class="text-left px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($preview as $row)
                            <tr class="{{ $row['matched'] ? '' : 'opacity-50' }}">
                                <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $row['name'] }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $row['new_officer'] ?? $row['current_officer'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $row['new_email'] ?? $row['current_email'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $row['new_cug'] ?? $row['current_cug'] ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @if($row['matched'])
                                    <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400">Will update</span>
                                    @else
                                    <span class="badge bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400">No match — skipped</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="button" wire:click="apply" wire:loading.attr="disabled" wire:target="apply"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Confirm &amp; Update
                </button>
                <button type="button" wire:click="$set('step', 1)" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Back</button>
            </div>
        </div>
        @endif

    </div>
</div>
