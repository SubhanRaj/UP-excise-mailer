<div>
    <x-breadcrumb :items="[['name' => 'Mail Accounts', 'url' => route('admin.mail-accounts.index')], ['name' => $mailAccount ? $mailAccount->gmail_address : 'Add Mail Account']]" />

    @php
        $currentProvider = match($smtpHost) {
            'smtp.gmail.com' => 'gmail',
            'smtp.mgovcloud.in' => 'nic',
            default => 'custom',
        };
    @endphp

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Section</label>
                    <select wire:model="sectionId" required class="field-input @error('sectionId') field-error @enderror">
                        <option value="">— Select —</option>
                        @foreach($sections as $section)
                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                    @error('sectionId')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Provider</label>
                    <select id="provider" class="field-input">
                        <option value="gmail" @selected($currentProvider === 'gmail')>Gmail (App Password)</option>
                        <option value="nic" @selected($currentProvider === 'nic')>NIC Email (mGovCloud)</option>
                        <option value="custom" @selected($currentProvider === 'custom')>Custom SMTP</option>
                    </select>
                </div>
            </div>

            <div>
                <label id="address-label" class="field-label">{{ $currentProvider === 'nic' ? 'NIC Email Address' : ($currentProvider === 'custom' ? 'SMTP Username / From Address' : 'Gmail Address') }}</label>
                <input type="email" wire:model="gmailAddress" required class="field-input @error('gmailAddress') field-error @enderror">
                @error('gmailAddress')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label id="password-label" class="field-label">{{ $mailAccount ? 'App Password' : ($currentProvider === 'nic' ? 'Password' : ($currentProvider === 'custom' ? 'SMTP Password' : 'App Password')) }}</label>
                <input type="password" wire:model="appPassword" autocomplete="new-password"
                       placeholder="{{ $mailAccount ? 'Leave blank to keep the current password' : '' }}"
                       class="field-input @error('appPassword') field-error @enderror">
                <p id="password-hint" class="field-hint">
                    {{ $mailAccount ? 'Only fill this in to replace the stored password.' : ($currentProvider === 'nic' ? 'Your NIC email password — or an app-specific password if two-factor authentication is enabled.' : ($currentProvider === 'custom' ? 'Password for the SMTP account above.' : 'Gmail app password (Google One paid seat), not the account login password.')) }}
                </p>
                @error('appPassword')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div id="smtp-advanced" class="{{ $currentProvider === 'custom' || $errors->has('smtpHost') || $errors->has('smtpPort') ? '' : 'hidden' }} space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="field-label">SMTP Host</label>
                        <input type="text" id="smtp_host" wire:model="smtpHost" class="field-input @error('smtpHost') field-error @enderror">
                        @error('smtpHost')<p class="field-err-msg">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label">Connection Security</label>
                        <select id="auth_mode" class="field-input">
                            <option value="tls" @selected((int) $smtpPort !== 465)>TLS (port 587)</option>
                            <option value="ssl" @selected((int) $smtpPort === 465)>SSL (port 465)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="field-label">SMTP Port</label>
                    <input type="number" id="smtp_port" wire:model="smtpPort" class="field-input @error('smtpPort') field-error @enderror">
                    <p class="field-hint">Filled in automatically from Connection Security above — only change it if your provider uses a different port.</p>
                    @error('smtpPort')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Delay Between Sends (seconds)</label>
                    <input type="number" wire:model="throttleSeconds" required min="60" class="field-input @error('throttleSeconds') field-error @enderror">
                    <p class="field-hint">60s minimum — enforced for every send from this account (including retries and resends), not just the value here.</p>
                    @error('throttleSeconds')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Daily Send Cap</label>
                    <input type="number" wire:model="dailySendCap" placeholder="No limit" class="field-input @error('dailySendCap') field-error @enderror">
                    @error('dailySendCap')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                <label class="field-label">Reply Fetching (IMAP)</label>
                <p class="field-hint mb-2">Optional — lets campaigns on this account show replies received to this mailbox. Leave blank to skip.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <input type="text" id="imap_host" wire:model="imapHost" placeholder="imap.gmail.com" class="field-input @error('imapHost') field-error @enderror">
                        @error('imapHost')<p class="field-err-msg">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <input type="number" id="imap_port" wire:model="imapPort" placeholder="993" class="field-input @error('imapPort') field-error @enderror">
                        @error('imapPort')<p class="field-err-msg">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer select-none">
                <input type="checkbox" wire:model="isActive" class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                Active
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors disabled:opacity-60">
                    {{ $mailAccount ? 'Save Changes' : 'Create Mail Account' }}
                </button>
                <a href="{{ route('admin.mail-accounts.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const mailProviderPresets = {
                gmail: {
                    address: 'Gmail Address', password: 'App Password',
                    hint: {{ $mailAccount ? \Illuminate\Support\Js::from('Only fill this in to replace the stored password.') : \Illuminate\Support\Js::from('Gmail app password (Google One paid seat), not the account login password.') }},
                    host: 'smtp.gmail.com', port: 587, imapHost: 'imap.gmail.com', imapPort: 993,
                },
                nic: {
                    address: 'NIC Email Address', password: 'Password',
                    hint: {{ $mailAccount ? \Illuminate\Support\Js::from('Only fill this in to replace the stored password.') : \Illuminate\Support\Js::from('Your NIC email password — or an app-specific password if two-factor authentication is enabled.') }},
                    host: 'smtp.mgovcloud.in', port: 587, imapHost: 'imap.mgovcloud.in', imapPort: 993,
                },
                custom: {
                    address: 'SMTP Username / From Address', password: 'SMTP Password',
                    hint: {{ $mailAccount ? \Illuminate\Support\Js::from('Only fill this in to replace the stored password.') : \Illuminate\Support\Js::from('Password for the SMTP account above.') }},
                    host: '', port: '', imapHost: '', imapPort: 993,
                },
            };

            // Livewire's wire:model listens for a native 'input' event — setting .value via JS
            // (as the provider-preset switch does) doesn't fire one on its own, so every
            // programmatic value change here must dispatch it manually or the component's
            // server-side state silently falls out of sync with what the field shows.
            function setAndSync(id, value) {
                const el = document.getElementById(id);
                if (!el) return;
                el.value = value;
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }

            document.getElementById('provider')?.addEventListener('change', function () {
                const preset = mailProviderPresets[this.value];
                document.getElementById('address-label').textContent = preset.address;
                document.getElementById('password-label').textContent = preset.password;
                document.getElementById('password-hint').textContent = preset.hint;
                setAndSync('smtp_host', preset.host);
                setAndSync('smtp_port', preset.port);
                document.getElementById('auth_mode').value = preset.port === 465 ? 'ssl' : 'tls';
                setAndSync('imap_host', preset.imapHost);
                setAndSync('imap_port', preset.imapPort);
                // Gmail and NIC's SMTP settings are well-documented and already filled in above —
                // only Custom SMTP needs the admin to see/edit them directly.
                document.getElementById('smtp-advanced').classList.toggle('hidden', this.value !== 'custom');
            });

            document.getElementById('auth_mode')?.addEventListener('change', function () {
                setAndSync('smtp_port', this.value === 'ssl' ? 465 : 587);
            });
        })();
    </script>
</div>
