<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\Campaign;
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
                ->send(new CampaignMail($this->renderedSubject, $this->renderedBody, $recipient, $account));

            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('SendCampaignRecipientMail failed', [
                'campaign_recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            $recipient->update(['status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 500), 'failed_at' => now()]);
        }

        $this->markCampaignCompletedIfDone($recipient->campaign_id);
    }

    /**
     * Nothing else in this app ever transitions Campaign::status past 'queued' — every
     * individual recipient could be sent/failed and the campaign itself would still show
     * "Sending" forever. Flips it to 'completed' once no recipient is left pending/queued
     * (matches this batch's confirmed live incident where a real send delivered successfully
     * but the campaign kept showing as stuck).
     */
    private function markCampaignCompletedIfDone(int $campaignId): void
    {
        $stillPending = CampaignRecipient::where('campaign_id', $campaignId)
            ->whereIn('status', ['pending', 'queued'])
            ->exists();

        if (! $stillPending) {
            Campaign::where('id', $campaignId)->update(['status' => 'completed']);
        }
    }
}
