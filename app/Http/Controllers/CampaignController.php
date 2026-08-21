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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy($sort, $direction)
            ->paginate(50)
            ->withQueryString();

        return view('campaigns.show', compact('campaign', 'recipients', 'statusCounts', 'sort', 'direction'));
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
}
