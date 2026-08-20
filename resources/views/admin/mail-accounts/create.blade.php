<x-layout title="Add Mail Account" page-title="Add Mail Account" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Mail Accounts', 'url' => route('admin.mail-accounts.index')], ['name' => 'Add Mail Account']]" />

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('admin.mail-accounts.store') }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Section</label>
                    <select name="section_id" required class="field-input @error('section_id') field-error @enderror">
                        <option value="">— Select —</option>
                        @foreach($sections as $section)
                        <option value="{{ $section->id }}" {{ old('section_id') == $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                        @endforeach
                    </select>
                    @error('section_id')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Provider</label>
                    <select id="provider" class="field-input">
                        <option value="gmail">Gmail (App Password)</option>
                        <option value="custom">Custom SMTP</option>
                    </select>
                    <p class="field-hint">Just fills in the SMTP fields below — everything is stored as plain SMTP settings.</p>
                </div>
            </div>

            <div>
                <label id="address-label" class="field-label">Gmail Address</label>
                <input type="email" name="gmail_address" value="{{ old('gmail_address') }}" required class="field-input @error('gmail_address') field-error @enderror">
                @error('gmail_address')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label id="password-label" class="field-label">App Password</label>
                <input type="password" name="app_password" required autocomplete="new-password" class="field-input @error('app_password') field-error @enderror">
                <p id="password-hint" class="field-hint">Gmail app password (Google One paid seat), not the account login password.</p>
                @error('app_password')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">SMTP Host</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="{{ old('smtp_host', 'smtp.gmail.com') }}" required class="field-input @error('smtp_host') field-error @enderror">
                    @error('smtp_host')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">SMTP Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" value="{{ old('smtp_port', 587) }}" required class="field-input @error('smtp_port') field-error @enderror">
                    @error('smtp_port')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Throttle (seconds between sends)</label>
                    <input type="number" name="throttle_seconds" value="{{ old('throttle_seconds', 4) }}" required class="field-input @error('throttle_seconds') field-error @enderror">
                    @error('throttle_seconds')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Daily Send Cap</label>
                    <input type="number" name="daily_send_cap" value="{{ old('daily_send_cap') }}" placeholder="No limit" class="field-input @error('daily_send_cap') field-error @enderror">
                    @error('daily_send_cap')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer select-none">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                       class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                Active
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Create Mail Account
                </button>
                <a href="{{ route('admin.mail-accounts.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('provider')?.addEventListener('change', function () {
            const isGmail = this.value === 'gmail';
            document.getElementById('address-label').textContent = isGmail ? 'Gmail Address' : 'SMTP Username / From Address';
            document.getElementById('password-label').textContent = isGmail ? 'App Password' : 'SMTP Password';
            document.getElementById('password-hint').textContent = isGmail
                ? 'Gmail app password (Google One paid seat), not the account login password.'
                : 'Password for the SMTP account above.';
            document.getElementById('smtp_host').value = isGmail ? 'smtp.gmail.com' : '';
            document.getElementById('smtp_port').value = isGmail ? 587 : '';
        });
    </script>
</x-layout>
