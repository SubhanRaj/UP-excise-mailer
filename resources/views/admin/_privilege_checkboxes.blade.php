@php
    $privilegeLabels = [
        'users.manage' => 'Manage Users',
        'sections.manage' => 'Manage Sections',
        'mail-accounts.manage' => 'Manage Mail Accounts',
        'designations.manage' => 'Manage Designations',
        'templates.manage' => 'Manage Templates',
        'campaigns.send' => 'Send Campaigns',
        'recipients.manage' => 'Edit Zone/Division/District Contacts',
        'recipients.import' => 'Import Recipients',
        'activity-logs.view' => 'View Activity Logs',
        'test-email.send' => 'Send Test Email',
    ];

    // A non-SuperAdmin can only grant privileges they themselves hold — matches the server-side
    // guard in UserForm::save(), so the form never offers a choice that'll just 403.
    if (! auth()->user()->isAdmin()) {
        $privilegeLabels = array_intersect_key($privilegeLabels, array_flip(auth()->user()->privileges ?? []));
    }
@endphp

{{-- $wireModel: name of the Livewire component's array property, e.g. "privileges" or
     "defaultPrivileges" — bound via wire:model as a native HTML checkbox-array-to-PHP-array,
     same mechanism a plain <input type="checkbox" name="x[]"> form would use. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
    @foreach($privilegeLabels as $value => $label)
    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer select-none">
        <input type="checkbox" wire:model="{{ $wireModel }}" value="{{ $value }}"
               class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
        {{ $label }}
    </label>
    @endforeach
</div>
