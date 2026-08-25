<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\MailAccount;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignShowSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_show_renders_via_livewire(): void
    {
        $section = Section::create(['name' => 'Test Section', 'slug' => 'test-section']);
        $mailAccount = MailAccount::create([
            'section_id' => $section->id,
            'gmail_address' => 'test@example.com',
            'app_password' => 'secret',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['username' => 'testuser', 'role' => 'SuperAdmin', 'section_id' => $section->id]);
        $campaign = Campaign::create([
            'name' => 'Smoke Test Campaign',
            'slug' => Campaign::uniqueSlugFor('Smoke Test Campaign'),
            'mail_account_id' => $mailAccount->id,
            'subject' => 'Subject',
            'body' => 'Body',
            'recipient_scope' => 'all',
            'attachment_mode' => 'none',
            'status' => 'completed',
            'created_by' => $user->id,
        ]);
        CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'recipient_type' => 'manual',
            'name' => 'Test Recipient',
            'email' => 'recipient@example.com',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('campaigns.show', $campaign));

        $response->assertStatus(200);
        $response->assertSeeLivewire(\App\Livewire\CampaignShow::class);
        $response->assertSee($campaign->name);
        $response->assertSee('Test Recipient');
    }

    public function test_toggle_responded_and_search_work_without_a_page_reload(): void
    {
        $section = Section::create(['name' => 'Test Section', 'slug' => 'test-section']);
        $mailAccount = MailAccount::create([
            'section_id' => $section->id, 'gmail_address' => 'test@example.com', 'app_password' => 'secret',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'is_active' => true,
        ]);
        $user = User::factory()->create(['username' => 'testuser2', 'role' => 'SuperAdmin', 'section_id' => $section->id]);
        $campaign = Campaign::create([
            'name' => 'Action Test Campaign', 'slug' => Campaign::uniqueSlugFor('Action Test Campaign'),
            'mail_account_id' => $mailAccount->id, 'subject' => 'S', 'body' => 'B',
            'recipient_scope' => 'all', 'attachment_mode' => 'none', 'status' => 'completed', 'created_by' => $user->id,
        ]);
        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id, 'recipient_type' => 'manual',
            'name' => 'Jane District Officer', 'email' => 'jane@example.com', 'status' => 'sent', 'sent_at' => now(),
        ]);

        $this->actingAs($user);

        $component = Livewire::test(\App\Livewire\CampaignShow::class, ['campaign' => $campaign]);

        $this->assertNull($recipient->fresh()->responded_at);
        $component->call('toggleResponded', $recipient->id);
        $this->assertNotNull($recipient->fresh()->responded_at);
        $component->call('toggleResponded', $recipient->id);
        $this->assertNull($recipient->fresh()->responded_at);

        // No-page-reload search: setting the property and re-rendering (what wire:model.live
        // does under the hood) must filter the table without ever hitting a real HTTP route.
        $component->set('search', 'jane')->assertSee('Jane District Officer');
        $component->set('search', 'nobody-matches-this')->assertDontSee('Jane District Officer');
    }

    /**
     * Regression test for the "Not responded only" export coming back empty when a status filter
     * was still active on the page: the override used to only replace the `responded` param,
     * leaving the page's own status filter to silently intersect with it.
     */
    public function test_responded_export_override_ignores_the_pages_own_status_filter(): void
    {
        $section = Section::create(['name' => 'Test Section', 'slug' => 'test-section']);
        $mailAccount = MailAccount::create([
            'section_id' => $section->id, 'gmail_address' => 'test@example.com', 'app_password' => 'secret',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'is_active' => true,
        ]);
        $user = User::factory()->create(['username' => 'testuser3', 'role' => 'SuperAdmin', 'section_id' => $section->id]);
        $campaign = Campaign::create([
            'name' => 'Export Test Campaign', 'slug' => Campaign::uniqueSlugFor('Export Test Campaign'),
            'mail_account_id' => $mailAccount->id, 'subject' => 'S', 'body' => 'B',
            'recipient_scope' => 'all', 'attachment_mode' => 'none', 'status' => 'completed', 'created_by' => $user->id,
        ]);
        CampaignRecipient::create([
            'campaign_id' => $campaign->id, 'recipient_type' => 'manual',
            'name' => 'Responded Officer', 'email' => 'responded@example.com',
            'status' => 'sent', 'sent_at' => now(), 'responded_at' => now(),
        ]);
        CampaignRecipient::create([
            'campaign_id' => $campaign->id, 'recipient_type' => 'manual',
            'name' => 'Waiting Officer', 'email' => 'waiting@example.com',
            'status' => 'failed', 'sent_at' => null,
        ]);

        $this->actingAs($user);

        // A status filter left over from browsing the page (only 'sent' rows) would, before the
        // fix, still apply underneath the "Not responded only" override and hide the failed,
        // not-yet-responded recipient — since it's not status 'sent'.
        $component = Livewire::test(\App\Livewire\CampaignShow::class, ['campaign' => $campaign])
            ->set('statusFilter', 'sent')
            ->call('export', 'xlsx', 'no')
            ->assertFileDownloaded();

        $tmpFile = tempnam(sys_get_temp_dir(), 'export-test').'.xlsx';
        file_put_contents($tmpFile, base64_decode(data_get($component->effects, 'download.content')));

        $names = [];
        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($tmpFile);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $names[] = $row->toArray()[0];
            }
        }
        $reader->close();
        unlink($tmpFile);

        $this->assertContains('Waiting Officer', $names);
        $this->assertNotContains('Responded Officer', $names);
    }
}
