<?php

namespace App\Livewire\Admin;

use App\Models\MailAccount;
use Livewire\Component;

class MailAccountIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasPrivilege('mail-accounts.manage'), 403);
    }

    public function delete(int $mailAccountId): void
    {
        abort_unless(auth()->user()->hasPrivilege('mail-accounts.manage'), 403);

        MailAccount::findOrFail($mailAccountId)->delete();
        flash()->success('Mail account deleted.');
    }

    public function render()
    {
        return view('livewire.admin.mail-account-index', [
            'mailAccounts' => MailAccount::with('section')->orderBy('gmail_address')->get(),
        ])->layout('components.layout', ['pageTitle' => 'Mail Accounts', 'title' => 'Mail Accounts']);
    }
}
