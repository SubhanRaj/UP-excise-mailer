<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\MailAccount;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

    /**
     * Global "everything this app has ever tried to send" view, across every campaign —
     * per-campaign status (the CampaignShow Livewire component) only shows one campaign at a
     * time. Shows every status, not just 'sent', so a failed send doesn't just silently vanish
     * from the list — it shows up with its own badge instead.
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
}
