<?php

namespace App\Livewire;

use App\Models\RecipientList;
use App\Models\RecipientListItem;
use App\Services\RecipientImportParser;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class RecipientListImportWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    /** 'file' (upload + column mapping) or 'manual' (typed name/email pairs, no file needed). */
    public string $mode = 'file';

    #[Validate('required|string|max:150')]
    public string $listName = '';

    #[Validate('required|file|mimes:csv,txt,xlsx,pdf|max:10240')]
    public $file = null;

    public array $headers = [];

    public array $rows = [];

    public string $nameColumn = '';

    public string $emailColumn = '';

    /** One recipient per line — "Name, email@example.com" or a bare email. */
    public string $manualEntries = '';

    /**
     * Livewire's update endpoint (unlike the initial page GET) isn't covered by the route's
     * own 'recipients.import' privilege middleware, so every action method re-checks
     * independently — matches CampaignBuilder/TestEmailSender's pattern elsewhere in this app.
     */
    public function upload(): void
    {
        abort_unless(auth()->user()->hasPrivilege('recipients.import'), 403);

        $this->validate();

        $extension = $this->file->getClientOriginalExtension();
        $parsed = app(RecipientImportParser::class)->parse($this->file->getRealPath(), $extension);

        $this->headers = $parsed['headers'];
        $this->rows = $parsed['rows'];

        if (empty($this->rows)) {
            $this->addError('file', 'No rows could be read from this file.');

            return;
        }

        // PDF extraction already produces exactly [name, email] — nothing to map.
        if ($extension === 'pdf') {
            $this->nameColumn = '0';
            $this->emailColumn = '1';
            $this->step = 3;

            return;
        }

        $this->emailColumn = (string) $this->guessColumn($this->headers, ['email', 'e-mail']);
        $this->nameColumn = (string) $this->guessColumn($this->headers, ['name']);
        $this->step = 2;
    }

    public function confirmMapping(): void
    {
        abort_unless(auth()->user()->hasPrivilege('recipients.import'), 403);

        $this->validate([
            'emailColumn' => 'required',
        ]);

        $this->step = 3;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasPrivilege('recipients.import'), 403);

        $this->validate();

        $nameIdx = $this->nameColumn === '' ? null : (int) $this->nameColumn;
        $emailIdx = (int) $this->emailColumn;

        $items = collect($this->rows)
            ->map(fn (array $row) => [
                'name' => $nameIdx !== null ? ($row[$nameIdx] ?? null) : null,
                'email' => trim((string) ($row[$emailIdx] ?? '')),
            ])
            ->filter(fn (array $r) => filter_var($r['email'], FILTER_VALIDATE_EMAIL))
            ->values();

        if ($items->isEmpty()) {
            $this->addError('emailColumn', 'No valid email addresses found in that column.');

            return;
        }

        DB::transaction(function () use ($items) {
            $list = RecipientList::create([
                'name' => $this->listName,
                'source_type' => $this->file->getClientOriginalExtension(),
                'uploaded_by' => auth()->id(),
                'original_filename' => $this->file->getClientOriginalName(),
            ]);

            $list->items()->createMany($items->all());
        });

        flash()->success("Recipient list \"{$this->listName}\" imported — {$items->count()} recipient(s).");

        $this->redirectRoute('recipient-lists.index', navigate: true);
    }

    public function saveManual(): void
    {
        abort_unless(auth()->user()->hasPrivilege('recipients.import'), 403);

        $this->validate([
            'listName' => 'required|string|max:150',
            'manualEntries' => 'required|string',
        ]);

        $items = collect(preg_split('/\r?\n/', $this->manualEntries))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(function (string $line) {
                // "Name, email" if a comma splits it, otherwise the whole line is the email.
                [$name, $email] = str_contains($line, ',')
                    ? array_pad(explode(',', $line, 2), 2, '')
                    : [null, $line];

                return ['name' => $name !== null ? trim($name) ?: null : null, 'email' => trim($email)];
            })
            ->filter(fn (array $r) => filter_var($r['email'], FILTER_VALIDATE_EMAIL))
            ->unique('email')
            ->values();

        if ($items->isEmpty()) {
            $this->addError('manualEntries', 'No valid email addresses found — one per line, optionally "Name, email".');

            return;
        }

        DB::transaction(function () use ($items) {
            $list = RecipientList::create([
                'name' => $this->listName,
                'source_type' => 'manual',
                'uploaded_by' => auth()->id(),
            ]);

            $list->items()->createMany($items->all());
        });

        flash()->success("Recipient list \"{$this->listName}\" created — {$items->count()} recipient(s).");

        $this->redirectRoute('recipient-lists.index', navigate: true);
    }

    /** @param string[] $labels */
    private function guessColumn(array $headers, array $labels): ?int
    {
        foreach ($headers as $i => $header) {
            foreach ($labels as $label) {
                if (str_contains(strtolower($header), $label)) {
                    return $i;
                }
            }
        }

        return null;
    }

    public function render()
    {
        return view('livewire.recipient-list-import-wizard');
    }
}
