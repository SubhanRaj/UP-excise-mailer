<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignRecipientMail;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\MailAccount;
use App\Models\MailTemplate;
use App\Services\ImapReplyFetcher;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = Campaign::with(['mailAccount', 'createdBy'])
            ->withCount([
                'recipients',
                'recipients as sent_count' => fn ($q) => $q->where('status', 'sent'),
            ])
            ->latest()
            ->paginate(20);

        return view('campaigns.index', compact('campaigns'));
    }

    public function show(Campaign $campaign, Request $request): View
    {
        $campaign->load('mailAccount', 'template', 'createdBy');

        $statusCounts = $campaign->recipients()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $sortable = ['name', 'email', 'status', 'sent_at'];
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $recipients = $campaign->recipients()
            ->withCount('replies')
            ->with(['replies' => fn ($q) => $q->orderBy('received_at')])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy($sort, $direction)
            ->paginate(50)
            ->withQueryString();

        $availableAttachments = $campaign->attachment_mode === 'zip_per_recipient'
            ? $this->campaignZipFiles($campaign)
            : [];

        return view('campaigns.show', compact('campaign', 'recipients', 'statusCounts', 'sort', 'direction', 'availableAttachments'));
    }

    /**
     * Global "everything this app has ever tried to send" view, across every campaign —
     * per-campaign status (CampaignController::show()) only shows one campaign at a time.
     * Shows every status, not just 'sent', so a failed send doesn't just silently vanish from
     * the list — it shows up with its own badge instead.
     */
    public function sentMail(Request $request): View
    {
        $statusCounts = CampaignRecipient::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $sortable = ['name', 'email', 'status', 'activity'];
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'activity';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $sort === 'activity' ? DB::raw('COALESCE(sent_at, failed_at, created_at)') : $sort;

        $sent = CampaignRecipient::with('campaign')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy($orderColumn, $direction)
            ->paginate(50)
            ->withQueryString();

        $testSends = ActivityLog::where('action', 'test-email.send')
            ->with('user')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('campaigns.sent-mail', [
            'sent' => $sent, 'testSends' => $testSends, 'statusCounts' => $statusCounts,
            'sort' => $sort, 'direction' => $direction,
        ]);
    }

    /**
     * "Send Test" on a Mail Accounts row — stashes the account ID in session (one-time,
     * consumed by TestEmailSender::mount()) and redirects to the plain /campaigns/test-send
     * URL, so the ID never appears in a query string, browser history, or server access log.
     */
    public function prefillTestSend(Request $request): RedirectResponse
    {
        $request->validate(['mail_account_id' => 'required|integer|exists:mail_accounts,id']);

        $account = MailAccount::findOrFail($request->integer('mail_account_id'));
        abort_unless($request->user()->canUseMailAccount($account), 403);

        session(['test_send_mail_account_id' => $account->id]);

        return redirect()->route('campaigns.test-send');
    }

    public function retryRecipient(Campaign $campaign, CampaignRecipient $recipient): RedirectResponse
    {
        abort_unless($recipient->campaign_id === $campaign->id, 404);
        abort_unless($recipient->status === 'failed', 400);

        $vars = $recipient->resolveVars();

        if (empty($vars)) {
            flash()->error("Can't retry {$recipient->email} — the original zone/division/district/list entry no longer exists.");

            return back();
        }

        try {
            DB::transaction(function () use ($recipient, $campaign) {
                $recipient->update(['status' => 'pending', 'error_message' => null, 'failed_at' => null]);
                // Otherwise the campaign would keep showing "Sent" (completed) with a recipient
                // still pending underneath it — SendCampaignRecipientMail flips it back once
                // this settles.
                $campaign->update(['status' => 'queued']);
            });
        } catch (\Throwable $e) {
            Log::error('CampaignController@retryRecipient failed', ['recipient_id' => $recipient->id, 'error' => $e->getMessage()]);
            flash()->error("Couldn't queue a retry for {$recipient->email} — nothing was changed.");

            return back();
        }

        SendCampaignRecipientMail::dispatch(
            $recipient->id,
            MailTemplate::render($campaign->subject, $vars),
            MailTemplate::render($campaign->body, $vars),
        );

        flash()->success("Retrying {$recipient->email}.");

        return back();
    }

    /**
     * For a recipient actually handled outside this app — sent manually from the section's own
     * Zoho/Gmail inbox instead of through a campaign — clears the failed state without
     * dispatching another automated send. Only makes sense for a 'failed' row; a 'sent' one is
     * already resolved.
     */
    public function markAsSent(Campaign $campaign, CampaignRecipient $recipient): RedirectResponse
    {
        abort_unless($recipient->campaign_id === $campaign->id, 404);
        abort_unless($recipient->status === 'failed', 400);

        try {
            DB::transaction(function () use ($recipient, $campaign) {
                $recipient->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null, 'failed_at' => null]);

                $stillPending = $campaign->recipients()->whereIn('status', ['pending', 'queued'])->exists();
                if (! $stillPending) {
                    $campaign->update(['status' => 'completed']);
                }
            });
        } catch (\Throwable $e) {
            Log::error('CampaignController@markAsSent failed', ['recipient_id' => $recipient->id, 'error' => $e->getMessage()]);
            flash()->error("Couldn't mark {$recipient->email} as sent.");

            return back();
        }

        flash()->success("Marked {$recipient->email} as sent manually.");

        return back();
    }

    /**
     * Resends to a corrected address — for a district/division/zone/list contact whose on-file
     * email bounced or is simply dead (accepted with a 250 by their relay but never actually
     * read), not just a same-address retry. Allowed from 'sent' too, not only 'failed', since a
     * "successful" send against a dead mailbox looks identical to this app.
     */
    public function resendToEmail(Request $request, Campaign $campaign, CampaignRecipient $recipient): RedirectResponse
    {
        abort_unless($recipient->campaign_id === $campaign->id, 404);
        abort_unless(in_array($recipient->status, ['sent', 'failed'], true), 400);

        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $newEmail = $validated['email'];
        $saveToDirectory = $request->boolean('save_to_directory');

        // Never trust a raw path from the client — only a file this campaign's own zip
        // actually extracted is a legal choice, so the allowed list is recomputed here too.
        $attachmentPath = $recipient->attachment_path;
        $attachmentChanged = false;
        if ($campaign->attachment_mode === 'zip_per_recipient' && $request->has('attachment_path')) {
            $requested = (string) $request->input('attachment_path');
            $allowed = $this->campaignZipFiles($campaign);
            abort_unless($requested === '' || in_array($requested, $allowed, true), 422);
            $attachmentPath = $requested === '' ? null : $requested;
            $attachmentChanged = $attachmentPath !== $recipient->attachment_path;
        }

        $vars = $recipient->resolveVars();

        if (empty($vars)) {
            flash()->error("Can't resend to {$recipient->name} — the original zone/division/district/list entry no longer exists.");

            return back();
        }

        $vars['email'] = $newEmail; // always reflect the actual send target, even for types that don't otherwise carry it

        try {
            DB::transaction(function () use ($recipient, $campaign, $newEmail, $saveToDirectory, $attachmentPath, $attachmentChanged) {
                if ($saveToDirectory) {
                    $recipient->saveEmailToDirectory($newEmail);
                }

                $recipient->update([
                    'email' => $newEmail, 'status' => 'pending',
                    'error_message' => null, 'failed_at' => null, 'sent_at' => null,
                    'attachment_path' => $attachmentPath,
                    'matched_via' => $attachmentChanged ? 'manual' : $recipient->matched_via,
                ]);
                $campaign->update(['status' => 'queued']);
            });
        } catch (\Throwable $e) {
            Log::error('CampaignController@resendToEmail failed', ['recipient_id' => $recipient->id, 'error' => $e->getMessage()]);
            flash()->error("Couldn't queue a resend — nothing was changed.");

            return back();
        }

        SendCampaignRecipientMail::dispatch(
            $recipient->id,
            MailTemplate::render($campaign->subject, $vars),
            MailTemplate::render($campaign->body, $vars),
        );

        $note = match (true) {
            $saveToDirectory && $attachmentChanged => ' — saved as the new email on file, with the corrected attachment.',
            $saveToDirectory => ' — saved as the new email on file too.',
            $attachmentChanged => ' — with the corrected attachment.',
            default => '.',
        };
        flash()->success("Resending to {$newEmail}{$note}");

        return back();
    }

    public function fetchReplies(Campaign $campaign): RedirectResponse
    {
        $campaign->loadMissing('mailAccount');
        $account = $campaign->mailAccount;

        if (! $account || ! $account->repliesEnabled()) {
            flash()->error('This campaign\'s mail account has no IMAP settings configured — set them under Mail Accounts first.');

            return back();
        }

        try {
            $count = (new ImapReplyFetcher())->fetch($account);
        } catch (\Throwable $e) {
            Log::error('CampaignController@fetchReplies failed', ['mail_account_id' => $account->id, 'error' => $e->getMessage()]);
            flash()->error("Couldn't check for replies: {$e->getMessage()}");

            return back();
        }

        flash()->success($count > 0 ? "Found {$count} new ".str('reply')->plural($count).'.' : 'No new replies.');

        return back();
    }

    /** Files this campaign's zip actually extracted (excludes the uploaded zip itself), keyed by their storage path. */
    private function campaignZipFiles(Campaign $campaign): array
    {
        $sample = $campaign->recipients()->whereNotNull('attachment_path')->value('attachment_path');

        if (! $sample) {
            return [];
        }

        return collect(Storage::disk('local')->files(dirname($sample)))
            ->reject(fn (string $f) => str_ends_with($f, '.zip'))
            ->values()
            ->all();
    }
}
