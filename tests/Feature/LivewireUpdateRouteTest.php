<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LivewireUpdateRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_http_call_to_livewire_update_endpoint_still_works(): void
    {
        $user = User::factory()->create(['username' => 'lwuser', 'role' => 'SuperAdmin']);
        $this->actingAs($user);

        // Load the real page so we get a genuine signed component snapshot, then extract it
        // exactly like Livewire's own JS would, and POST a real update call — this exercises
        // the actual HTTP route + its new throttle:mutations + RequireLivewireHeaders
        // middleware, not just the in-process Livewire::test() driver.
        $html = $this->get(route('admin.sections.index'))->getContent();

        preg_match('/wire:snapshot="(.*?)"\s/s', $html, $m);
        $this->assertNotEmpty($m, 'Could not find a wire:snapshot in the rendered page');
        $snapshot = html_entity_decode($m[1]);
        $snapshotData = json_decode($snapshot, true);
        $this->assertIsArray($snapshotData);

        $response = $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire-aedb22fb/update', [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ]);

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertArrayHasKey('components', $body);
    }
}
