<?php

namespace App\Livewire;

use App\Jobs\SendCampaignRecipientMail;
use App\Models\Campaign;
use App\Models\MailTemplate;
use App\Services\ImapReplyFetcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CampaignShow extends Component
{
    use WithPagination;

    public Campaign $campaign;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    #[Url(as: 'responded', history: true)]
    public string $respondedFilter = '';

    #[Url(history: true)]
    public string $sort = 'id';

    #[Url(history: true)]
    public string $direction = 'desc';

    /** Only one row's resend form open at a time — matches how it's actually used in practice. */
    public ?int $resendOpenId = null;

    public string $resendEmail = '';

    public bool $resendSaveToDirectory = true;

    public string $resendAttachmentPath = '';

    public function mount(Campaign $campaign): void
    {
        $campaign->load('mailAccount', 'template', 'createdBy');
        $this->campaign = $campaign;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRespondedFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['name', 'status'], true)) {
            return;
        }

        $this->direction = ($this->sort === $field && $this->direction === 'asc') ? 'desc' : 'asc';
        $this->sort = $field;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'statusFilter', 'respondedFilter');
        $this->resetPage();
    }

    public function toggleResend(int $recipientId): void
    {
        if ($this->resendOpenId === $recipientId) {
            $this->resendOpenId = null;

            return;
        }

        $recipient = $this->campaign->recipients()->findOrFail($recipientId);
        $this->resendOpenId = $recipientId;
        $this->resendEmail = $recipient->email;
        $this->resendSaveToDirectory = true;
        $this->resendAttachmentPath = $recipient->attachment_path ?? '';
    }

    public function retry(int $recipientId): void
    {
        abort_unless(auth()->user()->hasPrivilege('campaigns.send'), 403);

        $recipient = $this->campaign->recipients()->findOrFail($recipientId);
        abort_unless($recipient->status === 'failed', 400);

        $vars = $recipient->resolveVars();
        if (empty($vars)) {
            flash()->error("Can't retry {$recipient->email} — the original zone/division/district/list entry no longer exists.");

            return;
        }

        try {
            DB::transaction(function () use ($recipient) {
                $recipient->update(['status' => 'pending', 'error_message' => null, 'failed_at' => null]);
                // Otherwise the campaign would keep showing "Sent" (completed) with a recipient
                // still pending underneath it — SendCampaignRecipientMail flips it back once
                // this settles.
                $this->campaign->update(['status' => 'queued']);
            });
        } catch (\Throwable $e) {
            Log::error('CampaignShow@retry failed', ['recipient_id' => $recipient->id, 'error' => $e->getMessage()]);
            flash()->error("Couldn't queue a retry for {$recipient->email} — nothing was changed.");

            return;
        }

        SendCampaignRecipientMail::dispatch(
            $recipient->id,
            MailTemplate::render($this->campaign->subject, $vars),
            MailTemplate::render($this->campaign->body, $vars),
        );

        flash()->success("Retrying {$recipient->email} via {$this->campaign->mailAccount->gmail_address}.");
    }

    /**
     * For a recipient actually handled outside this app — sent manually from the section's own
     * inbox instead of through a campaign — clears the failed state without dispatching another
     * automated send. Only makes sense for a 'failed' row; a 'sent' one is already resolved.
     */
    public function markSent(int $recipientId): void
    {
        abort_unless(auth()->user()->hasPrivilege('campaigns.send'), 403);

        $recipient = $this->campaign->recipients()->findOrFail($recipientId);
        abort_unless($recipient->status === 'failed', 400);

        try {
            DB::transaction(function () use ($recipient) {
                $recipient->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null, 'failed_at' => null]);

                $stillPending = $this->campaign->recipients()->whereIn('status', ['pending', 'queued'])->exists();
                if (! $stillPending) {
                    $this->campaign->update(['status' => 'completed']);
                }
            });
        } catch (\Throwable $e) {
            Log::error('CampaignShow@markSent failed', ['recipient_id' => $recipient->id, 'error' => $e->getMessage()]);
            flash()->error("Couldn't mark {$recipient->email} as sent.");

            return;
        }

        flash()->success("Marked {$recipient->email} as sent manually.");
    }

    /**
     * Resends to a corrected address — for a district/division/zone/list contact whose on-file
     * email bounced or is simply dead, not just a same-address retry. Allowed from 'sent' too,
     * not only 'failed', since a "successful" send against a dead mailbox looks identical to
     * this app.
     */
    public function resend(int $recipientId): void
    {
        abort_unless(auth()->user()->hasPrivilege('campaigns.send'), 403);

        $recipient = $this->campaign->recipients()->findOrFail($recipientId);
        abort_unless(in_array($recipient->status, ['sent', 'failed'], true), 400);

        $this->validate(['resendEmail' => ['required', 'email', 'max:255']]);
        $newEmail = $this->resendEmail;
        $saveToDirectory = $this->resendSaveToDirectory;

        // Never trust a raw path from the client — only a file this campaign's own zip
        // actually extracted is a legal choice, so the allowed list is recomputed here too.
        $attachmentPath = $recipient->attachment_path;
        $attachmentChanged = false;
        if ($this->campaign->attachment_mode === 'zip_per_recipient') {
            $requested = $this->resendAttachmentPath;
            $allowed = $this->campaignZipFiles();
            abort_unless($requested === '' || in_array($requested, $allowed, true), 422);
            $attachmentPath = $requested === '' ? null : $requested;
            $attachmentChanged = $attachmentPath !== $recipient->attachment_path;
        }

        $vars = $recipient->resolveVars();
        if (empty($vars)) {
            flash()->error("Can't resend to {$recipient->name} — the original zone/division/district/list entry no longer exists.");

            return;
        }
        $vars['email'] = $newEmail;

        try {
            DB::transaction(function () use ($recipient, $newEmail, $saveToDirectory, $attachmentPath, $attachmentChanged) {
                if ($saveToDirectory) {
                    $recipient->saveEmailToDirectory($newEmail);
                }

                $recipient->update([
                    'email' => $newEmail, 'status' => 'pending',
                    'error_message' => null, 'failed_at' => null, 'sent_at' => null,
                    'attachment_path' => $attachmentPath,
                    'matched_via' => $attachmentChanged ? 'manual' : $recipient->matched_via,
                ]);
                $this->campaign->update(['status' => 'queued']);
            });
        } catch (\Throwable $e) {
            Log::error('CampaignShow@resend failed', ['recipient_id' => $recipient->id, 'error' => $e->getMessage()]);
            flash()->error("Couldn't queue a resend — nothing was changed.");

            return;
        }

        SendCampaignRecipientMail::dispatch(
            $recipient->id,
            MailTemplate::render($this->campaign->subject, $vars),
            MailTemplate::render($this->campaign->body, $vars),
        );

        $note = match (true) {
            $saveToDirectory && $attachmentChanged => ' — saved as the new email on file, with the corrected attachment.',
            $saveToDirectory => ' — saved as the new email on file too.',
            $attachmentChanged => ' — with the corrected attachment.',
            default => '.',
        };
        flash()->success("Resending to {$newEmail} via {$this->campaign->mailAccount->gmail_address}{$note}");

        $this->resendOpenId = null;
    }

    public function toggleResponded(int $recipientId): void
    {
        abort_unless(auth()->user()->hasPrivilege('campaigns.send'), 403);

        $recipient = $this->campaign->recipients()->findOrFail($recipientId);
        $recipient->update(['responded_at' => $recipient->responded_at ? null : now()]);
    }

    /** Bulk-toggles every recipient on the current page — the same "one page at a time" bulk shape as before. */
    public function bulkMarkResponded(bool $responded): void
    {
        abort_unless(auth()->user()->hasPrivilege('campaigns.send'), 403);

        $ids = $this->recipientsQuery()->paginate(50)->pluck('id');
        $count = $this->campaign->recipients()->whereIn('id', $ids)->update(['responded_at' => $responded ? now() : null]);

        flash()->success($responded
            ? "Marked {$count} ".str('recipient')->plural($count)." as responded."
            : "Marked {$count} ".str('recipient')->plural($count)." as not responded.");
    }

    public function fetchReplies(): void
    {
        abort_unless(auth()->user()->hasPrivilege('campaigns.send'), 403);

        $this->campaign->loadMissing('mailAccount');
        $account = $this->campaign->mailAccount;

        if (! $account || ! $account->repliesEnabled()) {
            flash()->error('This campaign\'s mail account has no IMAP settings configured — set them under Mail Accounts first.');

            return;
        }

        try {
            $count = (new ImapReplyFetcher())->fetch($account);
        } catch (\Throwable $e) {
            Log::error('CampaignShow@fetchReplies failed', ['mail_account_id' => $account->id, 'error' => $e->getMessage()]);
            flash()->error("Couldn't check for replies: {$e->getMessage()}");

            return;
        }

        flash()->success($count > 0
            ? "Found {$count} new ".str('reply')->plural($count)." via {$account->gmail_address}."
            : "No new replies via {$account->gmail_address}.");
    }

    private function recipientsQuery()
    {
        return $this->campaign->recipients()
            ->withCount('replies')
            ->with(['replies' => fn ($q) => $q->orderBy('received_at')])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->respondedFilter !== '', fn ($q) => $this->respondedFilter === 'yes' ? $q->whereNotNull('responded_at') : $q->whereNull('responded_at'))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy($this->sort, $this->direction);
    }

    /** Files this campaign's zip actually extracted (excludes the uploaded zip itself), keyed by their storage path. */
    private function campaignZipFiles(): array
    {
        $sample = $this->campaign->recipients()->whereNotNull('attachment_path')->value('attachment_path');
        if (! $sample) {
            return [];
        }

        return collect(Storage::disk('local')->files(dirname($sample)))
            ->reject(fn (string $f) => str_ends_with($f, '.zip'))
            ->values()
            ->all();
    }

    public function render()
    {
        $recipients = $this->recipientsQuery()->paginate(50);

        $statusCounts = $this->campaign->recipients()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $respondedCount = (clone $this->campaign->recipients())->whereNotNull('responded_at')->count();

        $availableAttachments = $this->campaign->attachment_mode === 'zip_per_recipient'
            ? $this->campaignZipFiles()
            : [];

        return view('livewire.campaign-show', [
            'recipients' => $recipients,
            'statusCounts' => $statusCounts,
            'respondedCount' => $respondedCount,
            'availableAttachments' => $availableAttachments,
            'hasInFlight' => $recipients->contains(fn ($r) => in_array($r->status, ['pending', 'queued'], true)),
        ])->layout('components.layout', ['pageTitle' => $this->campaign->name, 'title' => $this->campaign->name]);
    }
}
