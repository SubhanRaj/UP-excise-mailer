<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\CampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCampaignRecipientMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $campaignRecipientId,
        public string $renderedSubject,
        public string $renderedBody,
    ) {}

    public function handle(): void
    {
        $recipient = CampaignRecipient::with('campaign.mailAccount')->find($this->campaignRecipientId);

        if (! $recipient || $recipient->status !== 'pending') {
            return;
        }

        $account = $recipient->campaign->mailAccount;

        try {
            config(['mail.mailers.dynamic' => $account->mailerConfig()]);

            Mail::mailer('dynamic')
                ->to($recipient->email)
                ->send(new CampaignMail($this->renderedSubject, $this->renderedBody, $recipient));

            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('SendCampaignRecipientMail failed', [
                'campaign_recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            $recipient->update(['status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 500)]);
        }
    }
}
