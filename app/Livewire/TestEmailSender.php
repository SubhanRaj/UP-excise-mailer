<?php

namespace App\Livewire;

use App\Mail\CampaignMail;
use App\Models\ActivityLog;
use App\Models\CampaignRecipient;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * SuperAdmin-only sanity check: sends a template through Resend (the same trusted system
 * sender used for invites/OTP — config('mail.default'), not a per-section Gmail account) so
 * "is the software working" can be answered without a configured/working mail_account. Not a
 * Campaign — no campaigns/campaign_recipients rows, just a direct one-off send.
 */
#[Layout('components.layout', ['pageTitle' => 'Send Test Email'])]
class TestEmailSender extends Component
{
    public string $templateId = '';

    public string $recipientMode = 'user'; // user | manual

    public string $userId = '';

    public string $manualEmail = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->templateId = (string) (MailTemplate::where('name', 'Test Email — Do Not Action')->value('id') ?? '');
    }

    public function send(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate([
            'templateId' => 'required|exists:mail_templates,id',
            'userId' => 'required_if:recipientMode,user|nullable|exists:users,id',
            'manualEmail' => 'required_if:recipientMode,manual|nullable|email',
        ]);

        $email = $this->recipientMode === 'manual'
            ? $this->manualEmail
            : User::findOrFail($this->userId)->email;

        $template = MailTemplate::findOrFail($this->templateId);

        $vars = [
            'district' => 'Sample District', 'division' => 'Sample Division', 'zone' => 'Sample Zone',
            'officer' => auth()->user()->name, 'name' => auth()->user()->name, 'email' => $email,
        ];

        Mail::to($email)->send(new CampaignMail(
            MailTemplate::render($template->subject, $vars),
            MailTemplate::render($template->body, $vars),
            new CampaignRecipient(['email' => $email]),
        ));

        ActivityLog::record('test-email.send', request(), ['to' => $email, 'template_id' => $template->id]);

        flash()->success("Test email sent to {$email} via Resend.");
    }

    public function render()
    {
        return view('livewire.test-email-sender', [
            'templates' => MailTemplate::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
}
