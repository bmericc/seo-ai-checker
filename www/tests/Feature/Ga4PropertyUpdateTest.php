<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ga4PropertyUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_owner_can_set_the_ga4_property_id(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->patch(route('domains.ga4-property.update', $domain), [
            'ga4_property_id' => '123456789',
        ]);

        $response->assertRedirect(route('domains.show', $domain));
        $this->assertSame('123456789', $domain->fresh()->ga4_property_id);
    }

    public function test_blank_value_clears_the_ga4_property_id(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id, 'ga4_property_id' => '123456789']);

        $this->actingAs($user)->patch(route('domains.ga4-property.update', $domain), ['ga4_property_id' => '']);

        $this->assertNull($domain->fresh()->ga4_property_id);
    }

    public function test_non_owner_cannot_update_the_ga4_property_id(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $otherUser = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $otherUser->id]);

        $this->actingAs($user)
            ->patch(route('domains.ga4-property.update', $domain), ['ga4_property_id' => '123456789'])
            ->assertStatus(403);
    }
}
