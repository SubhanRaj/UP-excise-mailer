<x-layout title="Edit Zone" page-title="Edit Zone" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Recipients', 'url' => route('recipients.index', ['tab' => 'zones'])], ['name' => $zone->name]]" />

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('recipients.zones.update', $zone) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="field-label">Zone Name</label>
                <input type="text" name="name" value="{{ old('name', $zone->name) }}" required class="field-input @error('name') field-error @enderror">
                <p class="field-hint">Hindi or English — both are accepted.</p>
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">JEC Name</label>
                    <input type="text" name="jc_name" value="{{ old('jc_name', $zone->jc_name) }}" class="field-input @error('jc_name') field-error @enderror">
                    @error('jc_name')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">JEC CUG</label>
                    <input type="text" name="jc_cug" value="{{ old('jc_cug', $zone->jc_cug) }}" class="field-input @error('jc_cug') field-error @enderror">
                    @error('jc_cug')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="field-label">JEC Email</label>
                <input type="email" name="jc_email" value="{{ old('jc_email', $zone->jc_email) }}" class="field-input @error('jc_email') field-error @enderror">
                @error('jc_email')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('recipients.index', ['tab' => 'zones']) }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
