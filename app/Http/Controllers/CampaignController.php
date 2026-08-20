<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignRecipientMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\MailTemplate;
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

        return view('campaigns.sent-mail', ['sent' => $sent]);
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

        SendCampaignRecipientMail::dispatch(
            $recipient->id,
            MailTemplate::render($campaign->subject, $vars),
            MailTemplate::render($campaign->body, $vars),
        );

        flash()->success("Retrying {$recipient->email}.");

        return back();
    }
}
