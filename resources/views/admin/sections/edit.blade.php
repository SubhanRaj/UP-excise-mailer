<x-layout title="Edit Section" page-title="Edit Section" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Sections', 'url' => route('admin.sections.index')], ['name' => $section->name]]" />

    <div class="max-w-lg bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('admin.sections.update', $section) }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div>
                <label class="field-label">Name</label>
                <input type="text" name="name" value="{{ old('name', $section->name) }}" required class="field-input @error('name') field-error @enderror">
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Section Email (optional)</label>
                <input type="email" name="email" value="{{ old('email', $section->email) }}" placeholder="section@excise.up.gov.in" class="field-input @error('email') field-error @enderror">
                <p class="field-hint">For receiving mail, not sending — sending still goes through a Mail Account below.</p>
                @error('email')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Section Head (optional)</label>
                <input type="text" name="head_name" value="{{ old('head_name', $section->head_name) }}" placeholder="e.g. name or designation" class="field-input @error('head_name') field-error @enderror">
                @error('head_name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('admin.sections.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
