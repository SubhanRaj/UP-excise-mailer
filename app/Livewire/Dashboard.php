<?php

namespace App\Livewire;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\District;
use App\Models\Division;
use App\Models\MailAccount;
use App\Models\MailTemplate;
use App\Models\RecipientList;
use App\Models\Zone;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['pageTitle' => 'Dashboard'])]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.dashboard', [
            'zoneCount' => Zone::count(),
            'divisionCount' => Division::count(),
            'districtCount' => District::count(),
            'mailAccountCount' => MailAccount::where('is_active', true)->count(),
            'recipientListCount' => RecipientList::count(),
            'templateCount' => MailTemplate::count(),
            'totalSentCount' => CampaignRecipient::whereNotNull('sent_at')->count(),
            'totalFailedCount' => CampaignRecipient::where('status', 'failed')->count(),
            'totalRespondedCount' => CampaignRecipient::whereNotNull('responded_at')->count(),
            'responseRate' => $this->responseRate(),
            'recentCampaigns' => Campaign::with('mailAccount')
                ->withCount([
                    'recipients',
                    'recipients as sent_count' => fn ($q) => $q->where('status', 'sent'),
                    'recipients as responded_count' => fn ($q) => $q->whereNotNull('responded_at'),
                ])
                ->latest()->limit(5)->get(),
            'sendVolume' => $this->sendVolumeByDay(),
        ]);
    }

    /** Responded ÷ sent, across every campaign — null (not 0%) when nothing's gone out yet, so the card can show "—" instead of a misleading 0%. */
    private function responseRate(): ?float
    {
        $sent = CampaignRecipient::whereNotNull('sent_at')->count();

        return $sent > 0 ? round(CampaignRecipient::whereNotNull('responded_at')->count() / $sent * 100, 1) : null;
    }

    /** Sent-email counts for the last 14 days, zero-filled — feeds the dashboard's Chart.js line chart. */
    private function sendVolumeByDay(): array
    {
        $since = now()->subDays(13)->startOfDay();

        $counts = CampaignRecipient::whereNotNull('sent_at')
            ->where('sent_at', '>=', $since)
            ->selectRaw('DATE(sent_at) as d, count(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $data = [];
        for ($day = $since->copy(); $day->lte(now()); $day->addDay()) {
            $labels[] = $day->format('d M');
            $data[] = $counts->get($day->format('Y-m-d'), 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
