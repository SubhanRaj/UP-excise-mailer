<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
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
}
