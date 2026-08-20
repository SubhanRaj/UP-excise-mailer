<x-layout title="Add User" page-title="Add User" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Users', 'url' => route('admin.users.index')], ['name' => 'Add User']]" />

    <div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('admin.users.store') }}" id="user-form" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="field-input @error('name') field-error @enderror">
                    @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="field-input @error('email') field-error @enderror">
                    @error('email')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Mobile</label>
                    <input type="text" name="mobile" value="{{ old('mobile') }}" maxlength="10" class="field-input @error('mobile') field-error @enderror">
                    @error('mobile')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Section</label>
                    <select name="section_id" class="field-input @error('section_id') field-error @enderror">
                        <option value="">— None —</option>
                        @foreach($sections as $section)
                        <option value="{{ $section->id }}" {{ old('section_id') == $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                        @endforeach
                    </select>
                    @error('section_id')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-3">
                <i class="ti ti-mail-forward flex-shrink-0 mt-0.5"></i>
                <span>No password to set here — an activation email with a one-time link will be sent to this address, and the officer sets their own password.</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="field-label">Designation</label>
                    <select name="designation_id" id="designation_id" class="field-input">
                        <option value="">— None —</option>
                        @foreach($designations as $designation)
                        <option value="{{ $designation->id }}" data-privileges='@json($designation->default_privileges ?? [])' {{ old('designation_id') == $designation->id ? 'selected' : '' }}>
                            {{ $designation->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Post / Charge</label>
                    <input type="text" name="post" value="{{ old('post') }}" placeholder="e.g. Prevention &amp; Enforcement" class="field-input @error('post') field-error @enderror">
                    @error('post')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Role</label>
                    <select name="role" required class="field-input @error('role') field-error @enderror">
                        <option value="User" {{ old('role', 'User') === 'User' ? 'selected' : '' }}>User</option>
                        <option value="Admin" {{ old('role') === 'Admin' ? 'selected' : '' }}>Admin</option>
                        @if(auth()->user()->isAdmin())
                        <option value="SuperAdmin" {{ old('role') === 'SuperAdmin' ? 'selected' : '' }}>SuperAdmin</option>
                        @endif
                    </select>
                    @error('role')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="field-hint -mt-3">Post/Charge is the specific posting, if any — Designation is the standard rank.</p>

            <div>
                <label class="field-label">Privileges</label>
                <p class="field-hint mb-2">SuperAdmin already has full access — these only matter for Admin/User accounts. Selecting a designation above fills in its defaults.</p>
                <div id="privileges-grid">
                    @include('admin._privilege_checkboxes', ['name' => 'privileges', 'checked' => old('privileges', [])])
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Create User
                </button>
                <a href="{{ route('admin.users.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('livewire:navigated', function () {
            document.getElementById('designation_id')?.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                const privileges = JSON.parse(opt.dataset.privileges || '[]');
                document.querySelectorAll('#privileges-grid input[type="checkbox"]').forEach(function (cb) {
                    cb.checked = privileges.includes(cb.value);
                });
            });
        });
    </script>
</x-layout>
