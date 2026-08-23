<x-layout title="Edit User" page-title="Edit User" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Users', 'url' => route('admin.users.index')], ['name' => $user->name]]" />

    {{-- Standalone sibling form for "Resend activation link" below — nesting it inside
         #editUserForm is invalid HTML: browsers close the OUTER form early at this form's
         </form>, silently dropping Designation/Post/Role/Privileges/Save out of the real form
         for any unactivated user. Same bug class as pdf-markdown-pipeline's M89 incident. --}}
    <form id="resendActivationForm" method="POST" action="{{ route('admin.users.resend-activation', $user) }}">
        @csrf
    </form>

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="field-input @error('name') field-error @enderror">
                    @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="field-input @error('email') field-error @enderror">
                    @error('email')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Mobile</label>
                    <input type="text" name="mobile" value="{{ old('mobile', $user->mobile) }}" maxlength="10" class="field-input @error('mobile') field-error @enderror">
                    @error('mobile')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Section</label>
                    <select name="section_id" class="field-input @error('section_id') field-error @enderror">
                        <option value="">— None —</option>
                        @foreach($sections as $section)
                        <option value="{{ $section->id }}" {{ old('section_id', $user->section_id) == $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                        @endforeach
                    </select>
                    @error('section_id')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            @if($user->email_verified_at === null)
            <div class="flex items-center justify-between gap-3 text-xs bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-4 py-3">
                <span class="flex items-start gap-2 text-amber-700 dark:text-amber-400">
                    <i class="ti ti-clock-exclamation flex-shrink-0 mt-0.5"></i>
                    This account hasn't been activated yet — the officer hasn't set a password.
                </span>
                <button type="submit" form="resendActivationForm" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline whitespace-nowrap flex-shrink-0">Resend activation link</button>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="field-label">Designation</label>
                    <select name="designation_id" id="designation_id" class="field-input">
                        <option value="">— None —</option>
                        @foreach($designations as $designation)
                        <option value="{{ $designation->id }}" data-privileges='@json($designation->default_privileges ?? [])' {{ old('designation_id', $user->designation_id) == $designation->id ? 'selected' : '' }}>
                            {{ $designation->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Post / Charge</label>
                    <input type="text" name="post" value="{{ old('post', $user->post) }}" placeholder="e.g. Prevention &amp; Enforcement" class="field-input @error('post') field-error @enderror">
                    @error('post')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Role</label>
                    <select name="role" required class="field-input @error('role') field-error @enderror">
                        <option value="User" {{ old('role', $user->role) === 'User' ? 'selected' : '' }}>User</option>
                        <option value="Admin" {{ old('role', $user->role) === 'Admin' ? 'selected' : '' }}>Admin</option>
                        @if(auth()->user()->isAdmin())
                        <option value="SuperAdmin" {{ old('role', $user->role) === 'SuperAdmin' ? 'selected' : '' }}>SuperAdmin</option>
                        @endif
                    </select>
                    @error('role')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="field-hint -mt-3">Post/Charge is the specific posting, if any — Designation is the standard rank.</p>

            <div>
                <label class="field-label">Privileges</label>
                <p class="field-hint mb-2">SuperAdmin already has full access — these only matter for Admin/User accounts.</p>
                <div id="privileges-grid">
                    @include('admin._privilege_checkboxes', ['name' => 'privileges', 'checked' => old('privileges', $user->privileges ?? [])])
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="{{ route('admin.users.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('designation_id')?.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            const privileges = JSON.parse(opt.dataset.privileges || '[]');
            document.querySelectorAll('#privileges-grid input[type="checkbox"]').forEach(function (cb) {
                cb.checked = privileges.includes(cb.value);
            });
        });
    </script>
</x-layout>
