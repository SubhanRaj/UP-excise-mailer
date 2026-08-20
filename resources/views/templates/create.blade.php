<x-layout title="Add Template" page-title="Add Template" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Templates', 'url' => route('templates.index')], ['name' => 'Add Template']]" />

    <div class="max-w-3xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('templates.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="field-label">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="field-input @error('name') field-error @enderror">
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Subject</label>
                <input type="text" name="subject" value="{{ old('subject') }}" required class="field-input @error('subject') field-error @enderror">
                <p class="field-hint">Use <code>@{{ variable }}</code> placeholders — e.g. @{{ district_name }}.</p>
                @error('subject')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Body</label>
                @include('templates._editor', ['value' => old('body', '')])
                <p class="field-hint">Use <code>@{{ variable }}</code> placeholders — e.g. @{{ district_name }} — anywhere in the text, filled in per recipient at send time.</p>
                @error('body')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Create Template
                </button>
                <a href="{{ route('templates.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
