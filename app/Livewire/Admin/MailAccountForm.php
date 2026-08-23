<?php

namespace App\Livewire\Admin;

use App\Models\MailAccount;
use App\Models\Section;
use Livewire\Component;

class MailAccountForm extends Component
{
    public ?MailAccount $mailAccount = null;

    public ?int $sectionId = null;

    public string $gmailAddress = '';

    /** Write-only — never pre-filled with the stored value, even on edit. */
    public string $appPassword = '';

    public string $smtpHost = 'smtp.gmail.com';

    public int $smtpPort = 587;

    public string $throttleSeconds = '4';

    public string $dailySendCap = '';

    public string $imapHost = '';

    public string $imapPort = '993';

    public bool $isActive = true;

    public function mount(?MailAccount $mailAccount = null): void
    {
        abort_unless(auth()->user()->hasPrivilege('mail-accounts.manage'), 403);

        $this->mailAccount = $mailAccount;
        $this->sectionId = $mailAccount->section_id ?? null;
        $this->gmailAddress = $mailAccount->gmail_address ?? '';
        $this->smtpHost = $mailAccount->smtp_host ?? 'smtp.gmail.com';
        $this->smtpPort = $mailAccount->smtp_port ?? 587;
        $this->throttleSeconds = (string) ($mailAccount->throttle_seconds ?? 4);
        $this->dailySendCap = $mailAccount?->daily_send_cap !== null ? (string) $mailAccount->daily_send_cap : '';
        $this->imapHost = $mailAccount->imap_host ?? '';
        $this->imapPort = $mailAccount?->imap_port !== null ? (string) $mailAccount->imap_port : '993';
        $this->isActive = $mailAccount->is_active ?? true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasPrivilege('mail-accounts.manage'), 403);

        $this->gmailAddress = strtolower($this->gmailAddress);

        $validated = $this->validate([
            'sectionId' => ['required', 'exists:sections,id'],
            'gmailAddress' => ['required', 'email', 'max:255'],
            // Required on create, optional on edit (blank = keep the existing password).
            'appPassword' => [$this->mailAccount ? 'nullable' : 'required', 'string', 'max:255'],
            'smtpHost' => ['required', 'string', 'max:255'],
            'smtpPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'throttleSeconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'dailySendCap' => ['nullable', 'integer', 'min:1'],
            'imapHost' => ['nullable', 'string', 'max:255'],
            'imapPort' => ['nullable', 'required_with:imapHost', 'integer', 'min:1', 'max:65535'],
        ]);

        $data = [
            'section_id' => $validated['sectionId'],
            'gmail_address' => $validated['gmailAddress'],
            'smtp_host' => $validated['smtpHost'],
            'smtp_port' => $validated['smtpPort'],
            'throttle_seconds' => $validated['throttleSeconds'],
            'daily_send_cap' => $validated['dailySendCap'] ?: null,
            'is_active' => $this->isActive,
            'imap_host' => $validated['imapHost'] ?: null,
            'imap_port' => $validated['imapHost'] ? $validated['imapPort'] : null,
        ];

        // Blank app_password on the edit form means "keep the existing one" — never overwrite a
        // working credential with an empty string.
        if ($validated['appPassword'] !== '' && $validated['appPassword'] !== null) {
            $data['app_password'] = $validated['appPassword'];
        }

        if ($this->mailAccount) {
            $this->mailAccount->update($data);
            flash()->success('Mail account updated.');
        } else {
            MailAccount::create([...$data, 'app_password' => $validated['appPassword']]);
            flash()->success('Mail account created.');
        }

        $this->redirectRoute('admin.mail-accounts.index', navigate: true);
    }

    public function render()
    {
        $title = $this->mailAccount ? "Edit {$this->mailAccount->gmail_address}" : 'Add Mail Account';

        return view('livewire.admin.mail-account-form', [
            'sections' => Section::orderBy('name')->get(),
        ])->layout('components.layout', ['pageTitle' => $title, 'title' => $title]);
    }
}
