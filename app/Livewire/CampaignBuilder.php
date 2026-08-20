<?php

namespace App\Livewire;

use App\Jobs\SendCampaignRecipientMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\District;
use App\Models\Division;
use App\Models\MailAccount;
use App\Models\MailTemplate;
use App\Models\RecipientList;
use App\Models\RecipientListItem;
use App\Models\Zone;
use App\Services\ZipRecipientMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use ZipArchive;

#[Layout('components.layout', ['pageTitle' => 'New Campaign'])]
class CampaignBuilder extends Component
{
    use WithFileUploads;

    public int $step = 1;

    // Step 1 — basics + recipients
    public string $name = '';

    public string $mailAccountId = '';

    public string $scope = 'districts';

    public array $selectedZoneIds = [];

    public array $selectedDivisionIds = [];

    public array $selectedDistrictIds = [];

    public string $recipientListId = '';

    // Newline/comma-separated addresses typed directly instead of importing a whole list —
    // for the "just add a couple of emails" case.
    public string $manualEmails = '';

    // Step 2 — template
    public string $templateId = '';

    public string $subject = '';

    public string $body = '';

    public bool $showNewTemplateModal = false;

    public string $newTemplateName = '';

    public string $newTemplateSubject = '';

    public string $newTemplateBody = '';

    // Step 3 — attachments
    public bool $wantsAttachment = false;

    public string $attachmentMode = 'none'; // single_file | zip_per_recipient

    public $singleFile = null;

    public $zipFile = null;

    public array $zipExtractedFiles = [];

    public array $zipMatchOverride = []; // recipient index => filename

    public ?string $zipExtractDir = null;

    // Set once confirmAndQueue() actually dispatches sends — guards against a second
    // "Confirm & Send" click (double-submit, stale back-button) firing the same batch twice.
    public bool $queued = false;

    public function goToStep(int $step): void
    {
        $this->step = $step;
    }

    public function insertSubjectVariable(string $variable): void
    {
        $this->subject .= '{{'.$variable.'}}';
    }

    public function updatedTemplateId(): void
    {
        if ($this->templateId === '') {
            return;
        }

        $template = MailTemplate::find($this->templateId);
        $this->subject = $template->subject ?? '';
        $this->body = $template->body ?? '';

        // The Body editor lives inside a wire:ignore div (Quill owns that DOM, not Livewire),
        // so a server-side change to $body alone never reaches the visible editor — push it in
        // directly via a browser event instead.
        $this->dispatch('quill-set-content', model: 'body', html: $this->body);
    }

    public function saveNewTemplate(): void
    {
        $this->validate([
            'newTemplateName' => 'required|string|max:150',
            'newTemplateSubject' => 'required|string|max:255',
            'newTemplateBody' => 'required|string',
        ]);

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $this->newTemplateSubject.' '.$this->newTemplateBody, $matches);

        $template = MailTemplate::create([
            'name' => $this->newTemplateName,
            'subject' => $this->newTemplateSubject,
            'body' => $this->newTemplateBody,
            'variables' => array_values(array_unique($matches[1])),
            'created_by' => auth()->id(),
        ]);

        $this->templateId = (string) $template->id;
        $this->subject = $template->subject;
        $this->body = $template->body;
        $this->showNewTemplateModal = false;
        $this->newTemplateName = '';
        $this->newTemplateSubject = '';
        $this->newTemplateBody = '';

        $this->dispatch('quill-set-content', model: 'body', html: $this->body);

        flash()->success('Template created and selected for this campaign.');
    }

    public function proceedToTemplate(): void
    {
        $this->validate([
            'name' => 'required|string|max:150',
            'mailAccountId' => 'required|exists:mail_accounts,id',
        ]);

        if ($this->candidateRecipients()->isEmpty()) {
            $this->addError('scope', 'No recipients match this selection — pick at least one, or check that emails are on file.');

            return;
        }

        $this->step = 2;
    }

    public function proceedToAttachments(): void
    {
        $this->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $this->step = 3;
    }

    public function uploadZip(): void
    {
        $this->validate(['zipFile' => 'required|file|mimes:zip|max:51200']);

        $dir = 'campaign-imports/'.uniqid();
        $zipPath = $this->zipFile->storeAs($dir, 'upload.zip', 'local');

        $zip = new ZipArchive();
        $zip->open(Storage::disk('local')->path($zipPath));

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (! str_ends_with($entry, '/')) {
                $files[] = $entry;
            }
        }
        $zip->extractTo(Storage::disk('local')->path($dir));
        $zip->close();

        $this->zipExtractDir = $dir;
        $this->zipExtractedFiles = $files;

        $recipients = $this->candidateRecipients()->values();
        $names = $recipients->mapWithKeys(fn ($r, $i) => [$i => $r['name']])->all();
        $matches = (new ZipRecipientMatcher())->match($names, $files);

        $this->zipMatchOverride = collect($matches)->map(fn ($m) => $m['filename'] ?? '')->all();

        flash()->success(count($files).' file(s) extracted — review the matches below.');
    }

    public function proceedToReview(): void
    {
        if ($this->wantsAttachment && $this->attachmentMode === 'single_file' && ! $this->singleFile) {
            $this->addError('singleFile', 'Choose a file to attach.');

            return;
        }

        if ($this->wantsAttachment && $this->attachmentMode === 'zip_per_recipient' && empty($this->zipExtractedFiles)) {
            $this->addError('zipFile', 'Upload and extract a zip first.');

            return;
        }

        $this->step = 4;
    }

    public function confirmAndQueue(): void
    {
        if ($this->queued) {
            return;
        }
        $this->queued = true;

        $account = MailAccount::findOrFail($this->mailAccountId);
        abort_unless(auth()->user()->canUseMailAccount($account), 403);

        $recipients = $this->candidateRecipients()->values();

        if ($recipients->isEmpty()) {
            $this->addError('scope', 'No recipients match this selection.');

            return;
        }

        $singleFilePath = null;
        if ($this->wantsAttachment && $this->attachmentMode === 'single_file' && $this->singleFile) {
            $singleFilePath = $this->singleFile->store('campaign-attachments', 'local');
        }

        // ponytail: counts today's already-sent rows once per queue action, not locked against
        // another campaign queuing concurrently on the same account — fine for this app's
        // actual usage (occasional manual campaigns), would need a lock/reservation if
        // campaigns ever get queued from multiple places at once.
        $alreadySentToday = $account->daily_send_cap
            ? CampaignRecipient::whereHas('campaign', fn ($q) => $q->where('mail_account_id', $account->id))
                ->where('status', 'sent')->whereDate('sent_at', today())->count()
            : 0;

        DB::transaction(function () use ($recipients, $account, $singleFilePath, $alreadySentToday) {
            $campaign = Campaign::create([
                'name' => $this->name,
                'mail_account_id' => $account->id,
                'template_id' => $this->templateId !== '' ? $this->templateId : null,
                'subject' => $this->subject,
                'body' => $this->body,
                'recipient_scope' => $this->scope,
                'attachment_mode' => $this->wantsAttachment ? $this->attachmentMode : 'none',
                'status' => 'queued',
                'created_by' => auth()->id(),
            ]);

            foreach ($recipients as $index => $r) {
                // wire:model on the match-override <select> doesn't itself constrain the value
                // to one of its rendered options — re-check against the actual extracted file
                // list server-side before it becomes a filesystem path.
                $override = $this->zipMatchOverride[$index] ?? null;
                $overrideIsValid = $override && in_array($override, $this->zipExtractedFiles, true);

                $attachmentPath = match (true) {
                    $singleFilePath !== null => $singleFilePath,
                    $this->wantsAttachment && $this->attachmentMode === 'zip_per_recipient' && $overrideIsValid =>
                        $this->zipExtractDir.'/'.$override,
                    default => null,
                };

                $recipient = CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'recipient_type' => $r['type'],
                    'recipient_ref_id' => $r['ref_id'],
                    'name' => $r['name'],
                    'email' => $r['email'],
                    'attachment_path' => $attachmentPath,
                    'matched_via' => $attachmentPath && $this->attachmentMode === 'zip_per_recipient' ? 'filename_auto' : ($attachmentPath ? null : 'none'),
                    'status' => 'pending',
                ]);

                SendCampaignRecipientMail::dispatch(
                    $recipient->id,
                    MailTemplate::render($this->subject, $r['vars']),
                    MailTemplate::render($this->body, $r['vars']),
                )->delay(now()->addSeconds($this->sendDelaySeconds($index, $account, $alreadySentToday)));
            }
        });

        flash()->success("Campaign \"{$this->name}\" queued — {$recipients->count()} email(s) will send over the next few minutes.");

        $this->redirectRoute('campaigns.index', navigate: true);
    }

    public function selectAllZones(): void
    {
        $this->selectedZoneIds = $this->selectedZoneIds ? [] : Zone::pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function selectAllDivisions(): void
    {
        $this->selectedDivisionIds = $this->selectedDivisionIds ? [] : Division::pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function selectAllDistricts(): void
    {
        $this->selectedDistrictIds = $this->selectedDistrictIds ? [] : District::pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /**
     * Staggers within a day by throttle_seconds as before; when the account has a
     * daily_send_cap, recipients past that many-per-day get pushed into the following day(s)
     * instead of bursting past the cap.
     */
    private function sendDelaySeconds(int $index, MailAccount $account, int $alreadySentToday): int
    {
        if (! $account->daily_send_cap) {
            return $account->throttle_seconds * $index;
        }

        $cap = $account->daily_send_cap;
        $remainingToday = max(0, $cap - $alreadySentToday);

        if ($index < $remainingToday) {
            return $account->throttle_seconds * $index;
        }

        $indexAfterToday = $index - $remainingToday;
        $day = 1 + intdiv($indexAfterToday, $cap);
        $positionInDay = $indexAfterToday % $cap;

        return $day * 86400 + $positionInDay * $account->throttle_seconds;
    }

    /** @return Collection<int, array{type:string, ref_id:int, name:?string, email:?string, vars:array}> */
    public function candidateRecipients(): Collection
    {
        $collection = match ($this->scope) {
            // "Everyone" = every zone, division, and district officer who has an email on
            // file — the whole org tree, not just the most granular level.
            'all' => Zone::get()->map(fn (Zone $z) => [
                'type' => 'zone', 'ref_id' => $z->id, 'name' => $z->officerDisplayName(), 'email' => $z->jc_email,
                'vars' => ['zone' => $z->name, 'officer' => $z->officerDisplayName(), 'cug' => $z->jc_cug],
            ])->concat(Division::with('zone')->get()->map(fn (Division $d) => [
                'type' => 'division', 'ref_id' => $d->id, 'name' => $d->officerDisplayName(), 'email' => $d->dc_email,
                'vars' => ['division' => $d->name, 'zone' => $d->zone?->name, 'officer' => $d->officerDisplayName(), 'cug' => $d->dc_cug],
            ]))->concat(District::with('division.zone')->get()->map(fn (District $d) => [
                'type' => 'district', 'ref_id' => $d->id, 'name' => $d->officerDisplayName(), 'email' => $d->deo_email,
                'vars' => [
                    'district' => $d->name, 'division' => $d->division?->name, 'zone' => $d->division?->zone?->name,
                    'officer' => $d->officerDisplayName(), 'cug' => $d->deo_cug,
                ],
            ])),
            'zones' => Zone::whereIn('id', $this->selectedZoneIds)->get()->map(fn (Zone $z) => [
                'type' => 'zone', 'ref_id' => $z->id, 'name' => $z->officerDisplayName(), 'email' => $z->jc_email,
                'vars' => ['zone' => $z->name, 'officer' => $z->officerDisplayName(), 'cug' => $z->jc_cug],
            ]),
            'divisions' => Division::with('zone')->whereIn('id', $this->selectedDivisionIds)->get()->map(fn (Division $d) => [
                'type' => 'division', 'ref_id' => $d->id, 'name' => $d->officerDisplayName(), 'email' => $d->dc_email,
                'vars' => ['division' => $d->name, 'zone' => $d->zone?->name, 'officer' => $d->officerDisplayName(), 'cug' => $d->dc_cug],
            ]),
            'districts' => District::with('division.zone')->whereIn('id', $this->selectedDistrictIds)->get()->map(fn (District $d) => [
                'type' => 'district', 'ref_id' => $d->id, 'name' => $d->officerDisplayName(), 'email' => $d->deo_email,
                'vars' => [
                    'district' => $d->name, 'division' => $d->division?->name, 'zone' => $d->division?->zone?->name,
                    'officer' => $d->officerDisplayName(), 'cug' => $d->deo_cug,
                ],
            ]),
            'recipient_list' => $this->recipientListId !== ''
                ? RecipientListItem::where('recipient_list_id', $this->recipientListId)->get()->map(fn (RecipientListItem $i) => [
                    'type' => 'list_item', 'ref_id' => $i->id, 'name' => $i->name, 'email' => $i->email,
                    'vars' => array_merge(['name' => $i->name, 'email' => $i->email], $i->extra ?? []),
                ])
                : collect(),
            'manual' => $this->parsedManualEmails()->map(fn (string $email) => [
                'type' => 'manual', 'ref_id' => null, 'name' => $email, 'email' => $email,
                'vars' => ['name' => $email, 'email' => $email],
            ]),
            default => collect(),
        };

        return $collection->filter(fn (array $r) => filter_var($r['email'], FILTER_VALIDATE_EMAIL))->values();
    }

    public function getRecipientCountProperty(): int
    {
        return $this->candidateRecipients()->count();
    }

    /** Splits the typed textarea on commas/newlines, trims, and drops blanks/duplicates. */
    private function parsedManualEmails(): Collection
    {
        return collect(preg_split('/[,\n]+/', $this->manualEmails))
            ->map(fn ($e) => trim($e))
            ->filter()
            ->unique()
            ->values();
    }

    /** Variable names offered by the picker for the current scope — plain words, no underscores. */
    public function getAvailableVariablesProperty(): array
    {
        return match ($this->scope) {
            'all', 'districts' => ['district', 'division', 'zone', 'officer', 'cug'],
            'zones' => ['zone', 'officer', 'cug'],
            'divisions' => ['division', 'zone', 'officer', 'cug'],
            'recipient_list' => $this->recipientListId !== ''
                ? collect($this->candidateRecipients()->first()['vars'] ?? ['name', 'email'])->keys()->all()
                : ['name', 'email'],
            'manual' => ['name', 'email'],
            default => [],
        };
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.campaign-builder', [
            'mailAccounts' => $user->isAdmin() ? MailAccount::where('is_active', true)->get() : MailAccount::where('is_active', true)->where('section_id', $user->section_id)->get(),
            'templates' => MailTemplate::orderBy('name')->get(),
            'zones' => Zone::orderBy('name')->get(),
            'divisions' => Division::with('zone')->orderBy('name')->get(),
            'districts' => District::with('division.zone')->orderBy('name')->get(),
            'recipientLists' => RecipientList::withCount('items')->orderBy('name')->get(),
        ]);
    }
}
