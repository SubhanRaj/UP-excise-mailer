<div>
    <x-breadcrumb :items="[['name' => 'Designations', 'url' => route('admin.designations.index')], ['name' => $designation ? $designation->name : 'Add Designation']]" />

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form wire:submit="save" class="space-y-5">
            <div>
                <label class="field-label">Name</label>
                <input type="text" wire:model="name" required class="field-input @error('name') field-error @enderror">
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Sort Order</label>
                <input type="number" wire:model="sortOrder" class="field-input @error('sortOrder') field-error @enderror">
                <p class="field-hint">Lower numbers appear first in dropdown lists.</p>
                @error('sortOrder')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Default Privileges</label>
                <p class="field-hint mb-2">Applied automatically when this designation is selected while creating or editing a user — an editable starting point, not a lock.</p>
                @include('admin._privilege_checkboxes', ['wireModel' => 'defaultPrivileges'])
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors disabled:opacity-60">
                    {{ $designation ? 'Save Changes' : 'Create Designation' }}
                </button>
                <a href="{{ route('admin.designations.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
