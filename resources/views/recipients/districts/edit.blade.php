<x-layout title="Edit District" page-title="Edit District" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Recipients', 'url' => route('recipients.index', ['tab' => 'districts'])], ['name' => $district->name]]" />

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('recipients.districts.update', $district) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="field-label">District Name</label>
                <input type="text" name="name" value="{{ old('name', $district->name) }}" required class="field-input @error('name') field-error @enderror">
                <p class="field-hint">Hindi or English — both are accepted.</p>
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">AEC (DEO) Name</label>
                    <input type="text" name="deo_name" value="{{ old('deo_name', $district->deo_name) }}" class="field-input @error('deo_name') field-error @enderror">
                    @error('deo_name')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">AEC (DEO) CUG</label>
                    <input type="text" name="deo_cug" value="{{ old('deo_cug', $district->deo_cug) }}" class="field-input @error('deo_cug') field-error @enderror">
                    @error('deo_cug')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="field-label">AEC (DEO) Email</label>
                <input type="email" name="deo_email" value="{{ old('deo_email', $district->deo_email) }}" class="field-input @error('deo_email') field-error @enderror">
                @error('deo_email')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('recipients.index', ['tab' => 'districts']) }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
