<?php

namespace App\Services;

use App\Models\CampaignReply;
use App\Models\CampaignRecipient;
use App\Models\MailAccount;
use Webklex\PHPIMAP\ClientManager;

class ImapReplyFetcher
{
    /**
     * Pulls INBOX messages since the account's last fetch (or 90 days back on a first run —
     * long enough to catch a slow reply without scanning a whole mailbox forever) and keeps
     * only the ones that are actually a reply to something this app sent, matched via the
     * Message-ID this app captured at send time against the incoming In-Reply-To/References
     * headers. Everything else in the inbox (the section's own unrelated mail) is left alone —
     * read from, never modified, never marked seen.
     *
     * Returns the number of newly-saved replies.
     */
    public function fetch(MailAccount $account): int
    {
        if (! $account->repliesEnabled()) {
            return 0;
        }

        $ourMessageIds = CampaignRecipient::whereHas('campaign', fn ($q) => $q->where('mail_account_id', $account->id))
            ->whereNotNull('message_id')
            ->pluck('id', 'message_id');

        if ($ourMessageIds->isEmpty()) {
            return 0;
        }

        $since = $account->imap_last_fetched_at?->subDay() ?? now()->subDays(90);

        $client = (new ClientManager())->make($account->imapConfig())->connect();
        $messages = $client->getFolder('INBOX')->query()->whereSince($since)->leaveUnread()->get();

        $saved = 0;

        foreach ($messages as $message) {
            // in_reply_to is stored as a single (usually one-id, occasionally space-joined)
            // string, unlike references which webklex already splits into individual ids.
            $inReplyTo = preg_split('/\s+/', (string) $message->in_reply_to->first(), -1, PREG_SPLIT_NO_EMPTY);
            $candidateIds = array_merge($inReplyTo, (array) $message->references->all());

            $recipientId = null;
            foreach ($candidateIds as $candidateId) {
                if ($ourMessageIds->has($candidateId)) {
                    $recipientId = $ourMessageIds->get($candidateId);
                    break;
                }
            }

            if (! $recipientId) {
                continue;
            }

            $messageId = $message->message_id->first();

            if (! $messageId || CampaignReply::where('message_id', $messageId)->exists()) {
                continue;
            }

            $from = $message->getFrom()->first();

            CampaignReply::create([
                'campaign_recipient_id' => $recipientId,
                'message_id' => $messageId,
                'from_address' => $from?->mail ?? 'unknown',
                'from_name' => $from?->personal,
                'subject' => $message->subject->first(),
                'body_text' => $message->getTextBody() ?: strip_tags($message->getHTMLBody()),
                'received_at' => $message->date->first() ?? now(),
            ]);

            $saved++;
        }

        $account->update(['imap_last_fetched_at' => now()]);

        return $saved;
    }
}
