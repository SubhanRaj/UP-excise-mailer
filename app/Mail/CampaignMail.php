<?php

namespace App\Mail;

use App\Models\CampaignRecipient;
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
    ) {}

    public function build(): self
    {
        $mail = $this->subject($this->renderedSubject)
            ->html($this->renderedBody);

        if ($this->recipient->attachment_path && Storage::disk('local')->exists($this->recipient->attachment_path)) {
            $mail->attach(Storage::disk('local')->path($this->recipient->attachment_path));
        }

        return $mail;
    }
}
