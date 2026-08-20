<?php

namespace App\Livewire;

use App\Models\Campaign;
use App\Models\District;
use App\Models\Division;
use App\Models\MailAccount;
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
            'recentCampaigns' => Campaign::with('mailAccount')->latest()->limit(5)->get(),
        ]);
    }
}
