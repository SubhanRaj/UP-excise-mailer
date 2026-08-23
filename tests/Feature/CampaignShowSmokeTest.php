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
}
