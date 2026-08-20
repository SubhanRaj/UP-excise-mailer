<?php

namespace App\Mail;

use App\Models\CampaignRecipient;
use App\Models\MailAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $renderedSubject,
        public string $renderedBody,
        public CampaignRecipient $recipient,
        public ?MailAccount $account = null,
    ) {}

    public function build(): self
    {
        $mail = $this->subject($this->renderedSubject)
            ->html($this->renderedBody);

        // When sending through a real mail_account (not the system Resend sender), the From
        // address must match the SMTP-authenticated mailbox — most relays (Gmail, and NIC's
        // mGovCloud in particular) reject the send with "553 Sender is not allowed to relay"
        // if the From header doesn't match who actually authenticated.
        if ($this->account) {
            $mail->from($this->account->gmail_address, $this->account->section?->name);
        }

        if ($this->recipient->attachment_path && Storage::disk('local')->exists($this->recipient->attachment_path)) {
            $mail->attach(Storage::disk('local')->path($this->recipient->attachment_path));
        }

        return $mail;
    }
}
