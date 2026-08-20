<div>
    <div class="max-w-3xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">

        <div class="flex items-center gap-2 mb-6 text-xs font-medium text-slate-400 dark:text-slate-500">
            <span class="{{ $step >= 1 ? 'text-indigo-600 dark:text-indigo-400' : '' }}">1. Upload</span>
            <i class="ti ti-chevron-right"></i>
            <span class="{{ $step >= 2 ? 'text-indigo-600 dark:text-indigo-400' : '' }}">2. Map Columns</span>
            <i class="ti ti-chevron-right"></i>
            <span class="{{ $step >= 3 ? 'text-indigo-600 dark:text-indigo-400' : '' }}">3. Preview &amp; Save</span>
        </div>

        @if($step === 1)
        <form wire:submit="upload" class="space-y-5">
            <div>
                <label class="field-label">List Name</label>
                <input type="text" wire:model="listName" required class="field-input @error('listName') field-error @enderror">
                @error('listName')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">File</label>
                <input type="file" wire:model="file" accept=".csv,.xlsx,.pdf" required class="field-input @error('file') field-error @enderror">
                <p class="field-hint">CSV, Excel, or a PDF you can select text in (a scanned or photographed PDF won't work). Max 10MB.</p>
                @error('file')<p class="field-err-msg">{{ $message }}</p>@enderror
                <div wire:loading wire:target="upload" class="text-xs text-slate-400 mt-2">Reading file…</div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="upload"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Upload File
                </button>
                <a href="{{ route('recipient-lists.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
        @endif

        @if($step === 2)
        <form wire:submit="confirmMapping" class="space-y-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ count($rows) }} row(s) found. Map which column holds each field.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Name Column</label>
                    <select wire:model="nameColumn" class="field-input">
                        <option value="">— None —</option>
                        @foreach($headers as $i => $header)
                        <option value="{{ $i }}">{{ $header ?: 'Column '.($i + 1) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Email Column</label>
                    <select wire:model="emailColumn" required class="field-input @error('emailColumn') field-error @enderror">
                        <option value="">— Select —</option>
                        @foreach($headers as $i => $header)
                        <option value="{{ $i }}">{{ $header ?: 'Column '.($i + 1) }}</option>
                        @endforeach
                    </select>
                    @error('emailColumn')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Preview
                </button>
                <button type="button" wire:click="$set('step', 1)" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Back</button>
            </div>
        </form>
        @endif

        @if($step === 3)
        <div class="space-y-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Importing <strong>{{ count($rows) }}</strong> row(s) into "<strong>{{ $listName }}</strong>". First 10 shown below.
            </p>

            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="overflow-x-auto max-h-80">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                            <tr>
                                <th class="text-left px-4 py-2">Name</th>
                                <th class="text-left px-4 py-2">Email</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach(array_slice($rows, 0, 10) as $row)
                            <tr>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $nameColumn !== '' ? ($row[(int) $nameColumn] ?? '—') : '—' }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $row[(int) $emailColumn] ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @error('emailColumn')<p class="field-err-msg">{{ $message }}</p>@enderror

            <div class="flex items-center gap-3 pt-2">
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Confirm &amp; Save
                </button>
                <button type="button" wire:click="$set('step', 2)" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Back</button>
            </div>
        </div>
        @endif

    </div>
</div>
