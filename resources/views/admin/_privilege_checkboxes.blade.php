@php
    $checked = $checked ?? [];
    $privilegeLabels = [
        'users.manage' => 'Manage Users',
        'sections.manage' => 'Manage Sections',
        'mail-accounts.manage' => 'Manage Mail Accounts',
        'templates.manage' => 'Manage Templates',
        'campaigns.send' => 'Send Campaigns',
        'recipients.import' => 'Import Recipients',
        'activity-logs.view' => 'View Activity Logs',
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
    @foreach($privilegeLabels as $value => $label)
    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer select-none">
        <input type="checkbox" name="{{ $name }}[]" value="{{ $value }}"
               {{ in_array($value, $checked ?? []) ? 'checked' : '' }}
               class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
        {{ $label }}
    </label>
    @endforeach
</div>
