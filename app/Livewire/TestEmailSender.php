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
 * Sends a template either through Resend (system sender, SuperAdmin-only — it's the shared
 * app-login sender, not something a section should be testing with) or through any mail
 * account the user is allowed to use, so each section's Gmail/NIC account can be tested too.
 * Reachable by anyone with the test-email.send privilege, not just SuperAdmin — a section's
 * own tech-support user can verify their own mail account works without needing full admin
 * access. Not a Campaign — no campaigns/campaign_recipients rows, just a direct one-off send.
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
        $user = auth()->user();
        abort_unless($user->hasPrivilege('test-email.send'), 403);

        $this->sendVia = $user->isAdmin() ? 'system' : 'mail_account';
        $this->templateId = (string) (MailTemplate::where('name', 'Test Email — Do Not Action')->value('id') ?? '');

        // Arrived from a "Send Test" button on a specific Mail Accounts row — pre-select that
        // account instead of making them pick it again from the dropdown. Passed via a POST +
        // one-time session value (pull = read once, then forget) rather than a ?mailAccountId=
        // query string, so an account ID never sits exposed in the URL/browser history/logs.
        $requestedAccountId = session()->pull('test_send_mail_account_id');
        if ($requestedAccountId) {
            $account = MailAccount::find($requestedAccountId);
            if ($account && $user->canUseMailAccount($account)) {
                $this->sendVia = 'mail_account';
                $this->mailAccountId = (string) $account->id;
            }
        }
    }

    public function send(): void
    {
        $user = auth()->user();
        abort_unless($user->hasPrivilege('test-email.send'), 403);
        // Resend is the shared system sender (also used for login OTP/invites) — reserved for
        // SuperAdmin so a section's test-send privilege can't be used to probe/abuse it.
        abort_if($this->sendVia === 'system' && ! $user->isAdmin(), 403);

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

        $via = 'Resend';
        $account = null;

        if ($this->sendVia === 'mail_account') {
            $account = MailAccount::findOrFail($this->mailAccountId);
            abort_unless(auth()->user()->canUseMailAccount($account), 403);
            $via = $account->gmail_address;
        }

        $mail = new CampaignMail(
            MailTemplate::render($template->subject, $vars),
            MailTemplate::render($template->body, $vars),
            new CampaignRecipient(['email' => $email]),
            $account,
        );

        try {
            if ($account) {
                config(['mail.mailers.dynamic' => $account->mailerConfig()]);
                Mail::mailer('dynamic')->to($email)->send($mail);
            } else {
                Mail::to($email)->send($mail);
            }

            ActivityLog::record('test-email.send', request(), [
                'to' => $email, 'template_id' => $template->id, 'via' => $via, 'status' => 'sent',
            ]);

            // flash()->success() only renders on the *next full page load* (php-flasher's
            // Blade directive is server-rendered) — this action never navigates anywhere, so
            // a flashed message would just sit unused in the session. Dispatching a Livewire
            // browser event instead, rendered as a toast by the listener in layout.blade.php.
            $this->dispatch('toast', type: 'success', message: "Test email sent to {$email} via {$via}.");
        } catch (\Throwable $e) {
            ActivityLog::record('test-email.send', request(), [
                'to' => $email, 'template_id' => $template->id, 'via' => $via, 'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 500),
            ]);

            $this->dispatch('toast', type: 'error', message: "Test email to {$email} failed: ".substr($e->getMessage(), 0, 200));
        }
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
