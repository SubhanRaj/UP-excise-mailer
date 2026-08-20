<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Division;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class RecipientController extends Controller
{
    /** Downloads a pre-filled XLSX for bulk-updating officer name/email/CUG at the given level. */
    public function downloadTemplate(string $level): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(in_array($level, ['zone', 'division', 'district'], true), 404);

        [$rows, $officerLabel] = match ($level) {
            'zone' => [Zone::orderBy('name')->get()->map(fn (Zone $z) => [$z->name, $z->jc_name, $z->jc_email, $z->jc_cug]), 'JEC'],
            'division' => [Division::orderBy('name')->get()->map(fn (Division $d) => [$d->name, $d->dc_name, $d->dc_email, $d->dc_cug]), 'DEC'],
            default => [District::orderBy('name')->get()->map(fn (District $d) => [$d->name, $d->deo_name, $d->deo_email, $d->deo_cug]), 'DEO'],
        };

        $filename = "{$officerLabel}-directory.xlsx";

        return response()->streamDownload(function () use ($rows, $officerLabel) {
            // A plain openToFile()-style write to php://output — streamDownload already owns
            // the Content-Disposition/filename via its own headers, so no need for
            // openToBrowser()'s (this avoids setting the same header twice).
            $writer = new Writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(['Name', "{$officerLabel} Name", "{$officerLabel} Email", "{$officerLabel} CUG"]));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

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
