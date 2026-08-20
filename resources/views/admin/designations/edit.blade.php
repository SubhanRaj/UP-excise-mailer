<x-layout title="Edit Designation" page-title="Edit Designation" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Designations', 'url' => route('admin.designations.index')], ['name' => $designation->name]]" />

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <div class="mb-5 flex items-start gap-2 text-sm text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg px-4 py-3">
            <i class="ti ti-info-circle flex-shrink-0 mt-0.5"></i>
            <span>Changes here only affect the preset applied to new selections — existing users keep whatever privileges they already have.</span>
        </div>

        <form method="POST" action="{{ route('admin.designations.update', $designation) }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div>
                <label class="field-label">Name</label>
                <input type="text" name="name" value="{{ old('name', $designation->name) }}" required class="field-input @error('name') field-error @enderror">
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Sort Order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $designation->sort_order) }}" class="field-input @error('sort_order') field-error @enderror">
                @error('sort_order')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Default Privileges</label>
                @include('admin._privilege_checkboxes', ['name' => 'default_privileges', 'checked' => old('default_privileges', $designation->default_privileges ?? [])])
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('admin.designations.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
