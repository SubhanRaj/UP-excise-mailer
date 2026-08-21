<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\Division;
use App\Models\Zone;
use App\Services\RecipientImportParser;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bulk-updates the JEC/DEC/DEO officer name/email/CUG on the existing zones/divisions/
 * districts from an uploaded XLSX (never creates new zones/divisions/districts — the 5/18/75
 * count is fixed). Expects the exact column order the "Download Template" export produces:
 * Name, Officer Name, Officer Email, Officer CUG. Blank cells leave the existing value alone,
 * so a sheet only needs to carry what actually changed.
 */
#[Layout('components.layout', ['pageTitle' => 'Import Officer Directory'])]
class OfficerDirectoryImportWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public string $level = 'district';

    public $file = null;

    /** @var array<int, array{name:string, officer:?string, email:?string, cug:?string}> */
    public array $rows = [];

    /** @var array<int, array{id:int, current_officer:?string, current_email:?string, current_cug:?string, matched:bool}> */
    public array $preview = [];

    /**
     * Livewire's update endpoint (unlike the initial page GET) isn't covered by the route's
     * own 'is_admin' middleware, so every action method re-checks independently — matches
     * CampaignBuilder/TestEmailSender's pattern elsewhere in this app.
     */
    public function upload(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->validate(['file' => 'required|file|mimes:xlsx,csv|max:5120']);

        $parsed = app(RecipientImportParser::class)->parse($this->file->getRealPath(), $this->file->getClientOriginalExtension());

        $this->rows = collect($parsed['rows'])
            ->filter(fn (array $row) => trim((string) ($row[0] ?? '')) !== '')
            ->map(fn (array $row) => [
                'name' => trim((string) ($row[0] ?? '')),
                'officer' => trim((string) ($row[1] ?? '')) ?: null,
                'email' => trim((string) ($row[2] ?? '')) ?: null,
                'cug' => trim((string) ($row[3] ?? '')) ?: null,
            ])
            ->values()
            ->all();

        if (empty($this->rows)) {
            $this->addError('file', 'No rows found — make sure column A has the Zone/Division/District name.');

            return;
        }

        $models = $this->modelsByName();

        $this->preview = collect($this->rows)->map(function (array $row) use ($models) {
            $model = $models->get(strtolower($row['name']));

            return [
                'name' => $row['name'],
                'new_officer' => $row['officer'],
                'new_email' => $row['email'],
                'new_cug' => $row['cug'],
                'id' => $model?->id,
                'current_officer' => $model?->officerDisplayName(),
                'current_email' => $model?->{$this->emailColumn()},
                'current_cug' => $model?->{$this->cugColumn()},
                'matched' => $model !== null,
            ];
        })->all();

        $this->step = 2;
    }

    public function apply(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $nameCol = $this->nameColumn();
        $emailCol = $this->emailColumn();
        $cugCol = $this->cugColumn();
        $updated = 0;

        DB::transaction(function () use ($nameCol, $emailCol, $cugCol, &$updated) {
            foreach ($this->preview as $row) {
                if (! $row['matched']) {
                    continue;
                }

                $model = $this->modelClass()::find($row['id']);
                if (! $model) {
                    continue;
                }

                $changes = array_filter([
                    $nameCol => $row['new_officer'],
                    $emailCol => $row['new_email'],
                    $cugCol => $row['new_cug'],
                ], fn ($v) => $v !== null);

                if ($changes) {
                    $model->update($changes);
                    $updated++;
                }
            }
        });

        flash()->success("Officer directory updated — {$updated} ".($updated === 1 ? 'row' : 'rows').' changed.');

        $this->redirectRoute('recipients.index', ['tab' => $this->level.'s'], navigate: true);
    }

    private function modelClass(): string
    {
        return match ($this->level) {
            'zone' => Zone::class,
            'division' => Division::class,
            default => District::class,
        };
    }

    private function nameColumn(): string
    {
        return match ($this->level) {
            'zone' => 'jc_name',
            'division' => 'dc_name',
            default => 'deo_name',
        };
    }

    private function emailColumn(): string
    {
        return match ($this->level) {
            'zone' => 'jc_email',
            'division' => 'dc_email',
            default => 'deo_email',
        };
    }

    private function cugColumn(): string
    {
        return match ($this->level) {
            'zone' => 'jc_cug',
            'division' => 'dc_cug',
            default => 'deo_cug',
        };
    }

    /** @return \Illuminate\Support\Collection<string, Zone|Division|District> keyed by lowercased name */
    private function modelsByName(): \Illuminate\Support\Collection
    {
        return $this->modelClass()::all()->keyBy(fn ($m) => strtolower($m->name));
    }

    public function render()
    {
        return view('livewire.officer-directory-import-wizard');
    }
}
