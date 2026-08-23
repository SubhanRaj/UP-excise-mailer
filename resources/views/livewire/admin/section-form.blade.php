<div>
    <x-breadcrumb :items="[['name' => 'Sections', 'url' => route('admin.sections.index')], ['name' => $section ? $section->name : 'Add Section']]" />

    <div class="max-w-lg bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form wire:submit="save" class="space-y-5">
            <div>
                <label class="field-label">Name</label>
                <input type="text" wire:model="name" required class="field-input @error('name') field-error @enderror">
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Section Email (optional)</label>
                <input type="email" wire:model="email" placeholder="section@excise.up.gov.in" class="field-input @error('email') field-error @enderror">
                <p class="field-hint">For receiving mail, not sending — sending still goes through a Mail Account below.</p>
                @error('email')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Section Head (optional)</label>
                <input type="text" wire:model="headName" placeholder="e.g. name or designation" class="field-input @error('headName') field-error @enderror">
                @error('headName')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors disabled:opacity-60">
                    {{ $section ? 'Save Changes' : 'Create Section' }}
                </button>
                <a href="{{ route('admin.sections.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
