<x-layout title="Edit Mail Account" page-title="Edit Mail Account" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Mail Accounts', 'url' => route('admin.mail-accounts.index')], ['name' => $mailAccount->gmail_address]]" />

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('admin.mail-accounts.update', $mailAccount) }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Section</label>
                    <select name="section_id" required class="field-input @error('section_id') field-error @enderror">
                        @foreach($sections as $section)
                        <option value="{{ $section->id }}" {{ old('section_id', $mailAccount->section_id) == $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                        @endforeach
                    </select>
                    @error('section_id')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Provider</label>
                    @php
                        $currentProvider = match($mailAccount->smtp_host) {
                            'smtp.gmail.com' => 'gmail',
                            'smtp.mgovcloud.in' => 'nic',
                            default => 'custom',
                        };
                    @endphp
                    <select id="provider" class="field-input">
                        <option value="gmail" {{ $currentProvider === 'gmail' ? 'selected' : '' }}>Gmail (App Password)</option>
                        <option value="nic" {{ $currentProvider === 'nic' ? 'selected' : '' }}>NIC Email (mGovCloud)</option>
                        <option value="custom" {{ $currentProvider === 'custom' ? 'selected' : '' }}>Custom SMTP</option>
                    </select>
                </div>
            </div>

            <div>
                <label id="address-label" class="field-label">Gmail Address</label>
                <input type="email" name="gmail_address" value="{{ old('gmail_address', $mailAccount->gmail_address) }}" required class="field-input @error('gmail_address') field-error @enderror">
                @error('gmail_address')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label id="password-label" class="field-label">App Password</label>
                {{-- Write-only — never echo the stored value back into the form. --}}
                <input type="password" name="app_password" autocomplete="new-password" placeholder="Leave blank to keep the current password" class="field-input @error('app_password') field-error @enderror">
                <p id="password-hint" class="field-hint">Only fill this in to replace the stored password.</p>
                @error('app_password')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">SMTP Host</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="{{ old('smtp_host', $mailAccount->smtp_host) }}" required class="field-input @error('smtp_host') field-error @enderror">
                    @error('smtp_host')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Connection Security</label>
                    <select id="auth_mode" class="field-input">
                        <option value="tls" {{ (int) old('smtp_port', $mailAccount->smtp_port) !== 465 ? 'selected' : '' }}>TLS (port 587)</option>
                        <option value="ssl" {{ (int) old('smtp_port', $mailAccount->smtp_port) === 465 ? 'selected' : '' }}>SSL (port 465)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="field-label">SMTP Port</label>
                <input type="number" id="smtp_port" name="smtp_port" value="{{ old('smtp_port', $mailAccount->smtp_port) }}" required class="field-input @error('smtp_port') field-error @enderror">
                <p class="field-hint">Filled in automatically from Connection Security above — only change it if your provider uses a different port.</p>
                @error('smtp_port')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Delay Between Sends (seconds)</label>
                    <input type="number" name="throttle_seconds" value="{{ old('throttle_seconds', $mailAccount->throttle_seconds) }}" required class="field-input @error('throttle_seconds') field-error @enderror">
                    @error('throttle_seconds')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Daily Send Cap</label>
                    <input type="number" name="daily_send_cap" value="{{ old('daily_send_cap', $mailAccount->daily_send_cap) }}" placeholder="No limit" class="field-input @error('daily_send_cap') field-error @enderror">
                    @error('daily_send_cap')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer select-none">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $mailAccount->is_active) ? 'checked' : '' }}
                       class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                Active
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('admin.mail-accounts.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('livewire:navigated', function () {
            const mailProviderPresets = {
                gmail: { address: 'Gmail Address', password: 'App Password', host: 'smtp.gmail.com', port: 587 },
                nic: { address: 'NIC Email Address', password: 'Password', host: 'smtp.mgovcloud.in', port: 587 },
                custom: { address: 'SMTP Username / From Address', password: 'SMTP Password', host: '', port: '' },
            };

            document.getElementById('provider')?.addEventListener('change', function () {
                const preset = mailProviderPresets[this.value];
                document.getElementById('address-label').textContent = preset.address;
                document.getElementById('password-label').textContent = preset.password;
                document.getElementById('password-hint').textContent = 'Only fill this in to replace the stored password.';
                document.getElementById('smtp_host').value = preset.host;
                document.getElementById('smtp_port').value = preset.port;
                document.getElementById('auth_mode').value = preset.port === 465 ? 'ssl' : 'tls';
            });

            document.getElementById('auth_mode')?.addEventListener('change', function () {
                document.getElementById('smtp_port').value = this.value === 'ssl' ? 465 : 587;
            });
        });
    </script>
</x-layout>
