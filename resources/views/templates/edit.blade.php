<x-layout title="Edit Template" page-title="Edit Template" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Templates', 'url' => route('templates.index')], ['name' => $template->name]]" />

    <div class="max-w-3xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('templates.update', $template) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="field-label">Name</label>
                <input type="text" name="name" value="{{ old('name', $template->name) }}" required class="field-input @error('name') field-error @enderror">
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Subject</label>
                <input type="text" name="subject" value="{{ old('subject', $template->subject) }}" required class="field-input @error('subject') field-error @enderror">
                <p class="field-hint">Type a word in double curly braces to fill it in per recipient — e.g. @{{ district }}.</p>
                @error('subject')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Body</label>
                @include('templates._editor', ['value' => old('body', $template->body)])
                <p class="field-hint">Type a word in double curly braces anywhere in the text to fill it in per recipient — e.g. @{{ district }}.</p>
                @error('body')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('templates.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
