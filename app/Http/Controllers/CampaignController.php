<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignRecipientMail;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\MailAccount;
use App\Models\MailTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = Campaign::with(['mailAccount', 'createdBy'])
            ->withCount('recipients')
            ->latest()
            ->paginate(20);

        return view('campaigns.index', compact('campaigns'));
    }

    public function show(Campaign $campaign): View
    {
        $campaign->load('mailAccount', 'template', 'createdBy');
        $recipients = $campaign->recipients()->latest()->paginate(50);

        $statusCounts = $campaign->recipients()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return view('campaigns.show', compact('campaign', 'recipients', 'statusCounts'));
    }

    /** Global "what's actually gone out" view — per-campaign status only shows one campaign at a time. */
    public function sentMail(): View
    {
        $sent = CampaignRecipient::with('campaign')
            ->where('status', 'sent')
            ->latest('sent_at')
            ->paginate(50);

        $testSends = ActivityLog::where('action', 'test-email.send')
            ->with('user')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('campaigns.sent-mail', ['sent' => $sent, 'testSends' => $testSends]);
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

        $recipient->update(['status' => 'pending', 'error_message' => null]);
        // Otherwise the campaign would keep showing "Sent" (completed) with a recipient still
        // pending underneath it — SendCampaignRecipientMail flips it back once this settles.
        $campaign->update(['status' => 'queued']);

        SendCampaignRecipientMail::dispatch(
            $recipient->id,
            MailTemplate::render($campaign->subject, $vars),
            MailTemplate::render($campaign->body, $vars),
        );

        flash()->success("Retrying {$recipient->email}.");

        return back();
    }
}
