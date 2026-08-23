<div>
    <x-breadcrumb :items="[['name' => 'Users', 'url' => route('admin.users.index')], ['name' => $user ? $user->name : 'Add User']]" />

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Name</label>
                    <input type="text" wire:model="name" required class="field-input @error('name') field-error @enderror">
                    @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Email</label>
                    <input type="email" wire:model="email" required class="field-input @error('email') field-error @enderror">
                    @error('email')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Mobile</label>
                    <input type="text" wire:model="mobile" maxlength="10" class="field-input @error('mobile') field-error @enderror">
                    @error('mobile')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Section</label>
                    <select wire:model="sectionId" class="field-input @error('sectionId') field-error @enderror">
                        <option value="">— None —</option>
                        @foreach($sections as $section)
                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                    @error('sectionId')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            @if(! $user)
            <div class="flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-3">
                <i class="ti ti-mail-forward flex-shrink-0 mt-0.5"></i>
                <span>No password to set here — an activation email with a one-time link will be sent to this address, and the officer sets their own password.</span>
            </div>
            @elseif($user->email_verified_at === null)
            <div class="flex items-center justify-between gap-3 text-xs bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-4 py-3">
                <span class="flex items-start gap-2 text-amber-700 dark:text-amber-400">
                    <i class="ti ti-clock-exclamation flex-shrink-0 mt-0.5"></i>
                    This account hasn't been activated yet — the officer hasn't set a password.
                </span>
                <button type="button" wire:click="resendActivation" wire:loading.attr="disabled" wire:target="resendActivation"
                        class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline whitespace-nowrap flex-shrink-0 disabled:opacity-60">
                    Resend activation link
                </button>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="field-label">Designation</label>
                    <select wire:model.live="designationId" class="field-input">
                        <option value="">— None —</option>
                        @foreach($designations as $designation)
                        <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Post / Charge</label>
                    <input type="text" wire:model="post" placeholder="e.g. Prevention &amp; Enforcement" class="field-input @error('post') field-error @enderror">
                    @error('post')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Role</label>
                    <select wire:model="role" required class="field-input @error('role') field-error @enderror">
                        <option value="User">User</option>
                        <option value="Admin">Admin</option>
                        @if(auth()->user()->isAdmin())
                        <option value="SuperAdmin">SuperAdmin</option>
                        @endif
                    </select>
                    @error('role')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="field-hint -mt-3">Post/Charge is the specific posting, if any — Designation is the standard rank.</p>

            <div>
                <label class="field-label">Privileges</label>
                <p class="field-hint mb-2">SuperAdmin already has full access — these only matter for Admin/User accounts. Selecting a designation above fills in its defaults.</p>
                <div wire:loading.class="opacity-50" wire:target="designationId" class="transition-opacity">
                    @include('admin._privilege_checkboxes', ['wireModel' => 'privileges'])
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors disabled:opacity-60">
                    {{ $user ? 'Save Changes' : 'Create User' }}
                </button>
                <a href="{{ route('admin.users.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
