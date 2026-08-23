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

    public function export(Campaign $campaign, Request $request, string $format): mixed
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $recipients = $campaign->recipients()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('responded'), fn ($q) => $request->query('responded') === 'yes' ? $q->whereNotNull('responded_at') : $q->whereNull('responded_at'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->get();

        $filename = str($campaign->name)->slug()->append('-recipients');

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($recipients) {
                $writer = new \OpenSpout\Writer\XLSX\Writer();
                $writer->openToFile('php://output');
                // Header-row dropdowns in Excel itself — 3 columns (A-C), header + one row per
                // recipient (both 1-indexed, matching OpenSpout's AutoFilter coordinates).
                $writer->getCurrentSheet()->setAutoFilter(new \OpenSpout\Writer\AutoFilter(0, 1, 2, $recipients->count() + 1));
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Name', 'Status', 'Responded']));
                foreach ($recipients as $recipient) {
                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                        $recipient->name ?: '—',
                        $recipient->status === 'pending' ? 'Waiting' : ucfirst($recipient->status),
                        $recipient->responded_at ? 'Yes' : 'No',
                    ]));
                }
                $writer->close();
            }, "{$filename}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('campaigns.export-pdf', compact('campaign', 'recipients'));

        return $pdf->download("{$filename}.pdf");
    }
}
