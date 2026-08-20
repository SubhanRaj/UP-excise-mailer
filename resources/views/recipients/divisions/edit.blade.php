<x-layout title="Edit Division" page-title="Edit Division" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Recipients', 'url' => route('recipients.index', ['tab' => 'divisions'])], ['name' => $division->name]]" />

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('recipients.divisions.update', $division) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="field-label">Division Name</label>
                <input type="text" name="name" value="{{ old('name', $division->name) }}" required class="field-input @error('name') field-error @enderror">
                <p class="field-hint">Hindi or English — both are accepted.</p>
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">DEC Name</label>
                    <input type="text" name="dc_name" value="{{ old('dc_name', $division->dc_name) }}" class="field-input @error('dc_name') field-error @enderror">
                    @error('dc_name')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">DEC CUG</label>
                    <input type="text" name="dc_cug" value="{{ old('dc_cug', $division->dc_cug) }}" class="field-input @error('dc_cug') field-error @enderror">
                    @error('dc_cug')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="field-label">DEC Email</label>
                <input type="email" name="dc_email" value="{{ old('dc_email', $division->dc_email) }}" class="field-input @error('dc_email') field-error @enderror">
                @error('dc_email')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('recipients.index', ['tab' => 'divisions']) }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
