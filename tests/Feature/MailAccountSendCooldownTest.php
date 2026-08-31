<?php

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailAccountSendCooldownTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(): MailAccount
    {
        $slug = 'cooldown-test-section-'.uniqid();
        $section = Section::create(['name' => $slug, 'slug' => $slug]);

        return MailAccount::create([
            'section_id' => $section->id, 'gmail_address' => $slug.'@example.com', 'app_password' => 'secret',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'is_active' => true,
        ]);
    }

    public function test_first_send_needs_no_wait(): void
    {
        $account = $this->makeAccount();

        $this->assertSame(0, $account->reserveSendSlot());
    }

    public function test_a_second_immediate_claim_is_pushed_out_by_the_full_cooldown(): void
    {
        $account = $this->makeAccount();

        $account->reserveSendSlot();
        $wait = $account->reserveSendSlot();

        $this->assertGreaterThanOrEqual(MailAccount::SEND_COOLDOWN_SECONDS - 1, $wait);
        $this->assertLessThanOrEqual(MailAccount::SEND_COOLDOWN_SECONDS, $wait);
    }

    public function test_two_accounts_dont_share_a_cooldown(): void
    {
        $account = $this->makeAccount();
        $other = $this->makeAccount();

        $account->reserveSendSlot();

        $this->assertSame(0, $other->reserveSendSlot());
    }
}
