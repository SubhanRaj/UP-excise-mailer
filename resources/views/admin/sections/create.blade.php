<x-layout title="Add Section" page-title="Add Section" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Sections', 'url' => route('admin.sections.index')], ['name' => 'Add Section']]" />

    <div class="max-w-lg bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('admin.sections.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="field-label">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="field-input @error('name') field-error @enderror">
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Create Section
                </button>
                <a href="{{ route('admin.sections.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
