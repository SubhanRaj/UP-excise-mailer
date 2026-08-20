<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Division;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipientController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'zones');
        $tab = in_array($tab, ['zones', 'divisions', 'districts'], true) ? $tab : 'zones';

        return view('recipients.index', [
            'tab' => $tab,
            'zones' => $tab === 'zones' ? Zone::orderBy('name')->get() : null,
            'divisions' => $tab === 'divisions' ? Division::with('zone')->orderBy('name')->get() : null,
            'districts' => $tab === 'districts' ? District::with('division.zone')->orderBy('name')->get() : null,
        ]);
    }

    public function editZone(Zone $zone): View
    {
        return view('recipients.zones.edit', compact('zone'));
    }

    public function updateZone(Request $request, Zone $zone): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'jc_name' => ['nullable', 'string', 'max:150'],
            'jc_email' => ['nullable', 'email', 'max:255'],
            'jc_cug' => ['nullable', 'string', 'max:20'],
        ]);

        $zone->update($validated);

        flash()->success('Zone updated.');

        return redirect()->route('recipients.index', ['tab' => 'zones']);
    }

    public function editDivision(Division $division): View
    {
        return view('recipients.divisions.edit', compact('division'));
    }

    public function updateDivision(Request $request, Division $division): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'dc_name' => ['nullable', 'string', 'max:150'],
            'dc_email' => ['nullable', 'email', 'max:255'],
            'dc_cug' => ['nullable', 'string', 'max:20'],
        ]);

        $division->update($validated);

        flash()->success('Division updated.');

        return redirect()->route('recipients.index', ['tab' => 'divisions']);
    }

    public function editDistrict(District $district): View
    {
        return view('recipients.districts.edit', compact('district'));
    }

    public function updateDistrict(Request $request, District $district): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'deo_name' => ['nullable', 'string', 'max:150'],
            'deo_email' => ['nullable', 'email', 'max:255'],
            'deo_cug' => ['nullable', 'string', 'max:20'],
        ]);

        $district->update($validated);

        flash()->success('District updated.');

        return redirect()->route('recipients.index', ['tab' => 'districts']);
    }
}
