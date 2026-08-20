<?php

namespace App\Livewire;

use App\Mail\CampaignMail;
use App\Models\ActivityLog;
use App\Models\CampaignRecipient;
use App\Models\MailAccount;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * SuperAdmin-only sanity check: sends a template either through Resend (system sender) or
 * through any mail account the user is allowed to use, so each Gmail account can be tested
 * too. Not a Campaign — no campaigns/campaign_recipients rows, just a direct one-off send.
 */
#[Layout('components.layout', ['pageTitle' => 'Send Test Email'])]
class TestEmailSender extends Component
{
    public string $templateId = '';

    public string $recipientMode = 'user'; // user | manual

    public string $userId = '';

    public string $manualEmail = '';

    public string $sendVia = 'system'; // system | mail_account

    public string $mailAccountId = '';

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
            'mailAccountId' => 'required_if:sendVia,mail_account|nullable|exists:mail_accounts,id',
        ]);

        $email = $this->recipientMode === 'manual'
            ? $this->manualEmail
            : User::findOrFail($this->userId)->email;

        $template = MailTemplate::findOrFail($this->templateId);

        $vars = [
            'district' => 'Sample District', 'division' => 'Sample Division', 'zone' => 'Sample Zone',
            'officer' => auth()->user()->name, 'name' => auth()->user()->name, 'email' => $email,
        ];

        $mail = new CampaignMail(
            MailTemplate::render($template->subject, $vars),
            MailTemplate::render($template->body, $vars),
            new CampaignRecipient(['email' => $email]),
        );

        $via = 'Resend';

        if ($this->sendVia === 'mail_account') {
            $account = MailAccount::findOrFail($this->mailAccountId);
            abort_unless(auth()->user()->canUseMailAccount($account), 403);

            config(['mail.mailers.dynamic' => $account->mailerConfig()]);
            Mail::mailer('dynamic')->to($email)->send($mail);
            $via = $account->gmail_address;
        } else {
            Mail::to($email)->send($mail);
        }

        ActivityLog::record('test-email.send', request(), ['to' => $email, 'template_id' => $template->id, 'via' => $via]);

        flash()->success("Test email sent to {$email} via {$via}.");
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.test-email-sender', [
            'templates' => MailTemplate::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'mailAccounts' => $user->isAdmin()
                ? MailAccount::where('is_active', true)->get()
                : MailAccount::where('is_active', true)->where('section_id', $user->section_id)->get(),
        ]);
    }
}
