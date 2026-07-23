<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainLlmApiKey;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DomainLlmVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_admin_can_toggle_llm_visibility_for_any_domain(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $owner = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $owner->id]);

        $response = $this->actingAs($admin)->patch(route('domains.llm-visibility.update', $domain), [
            'llm_visibility_enabled' => true,
        ]);

        $response->assertRedirect(route('domains.show', $domain));
        $this->assertTrue($domain->fresh()->llm_visibility_enabled);
    }

    public function test_domain_owner_who_is_not_admin_cannot_toggle_llm_visibility(): void
    {
        $owner = User::factory()->create(['approved_at' => now(), 'is_admin' => false]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('domains.llm-visibility.update', $domain), ['llm_visibility_enabled' => true])
            ->assertForbidden();

        $this->assertFalse($domain->fresh()->llm_visibility_enabled);
    }

    public function test_admin_can_store_an_api_key_for_a_domain(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $admin->id]);

        $response = $this->actingAs($admin)->post(route('domains.llm-api-keys.store', $domain), [
            'provider' => 'openai',
            'label' => 'bireysel',
            'api_key' => 'sk-test-123456',
        ]);

        $response->assertRedirect(route('domains.show', $domain));
        $key = $domain->llmApiKeys()->first();
        $this->assertNotNull($key);
        $this->assertSame('openai', $key->provider);
        $this->assertSame('bireysel', $key->label);
        $this->assertSame('sk-test-123456', $key->api_key);
    }

    public function test_storing_a_key_for_the_same_provider_twice_updates_it_instead_of_duplicating(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $admin->id]);

        $this->actingAs($admin)->post(route('domains.llm-api-keys.store', $domain), [
            'provider' => 'openai',
            'label' => 'bireysel',
            'api_key' => 'sk-old',
        ]);
        $this->actingAs($admin)->post(route('domains.llm-api-keys.store', $domain), [
            'provider' => 'openai',
            'label' => 'musteri',
            'api_key' => 'sk-new',
        ]);

        $this->assertSame(1, $domain->llmApiKeys()->count());
        $key = $domain->llmApiKeys()->first();
        $this->assertSame('sk-new', $key->api_key);
        $this->assertSame('musteri', $key->label);
    }

    public function test_non_admin_cannot_store_an_api_key(): void
    {
        $owner = User::factory()->create(['approved_at' => now(), 'is_admin' => false]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $owner->id]);

        $this->actingAs($owner)->post(route('domains.llm-api-keys.store', $domain), [
            'provider' => 'openai',
            'label' => 'bireysel',
            'api_key' => 'sk-test',
        ])->assertForbidden();

        $this->assertSame(0, $domain->llmApiKeys()->count());
    }

    public function test_invalid_provider_is_rejected(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $admin->id]);

        $this->actingAs($admin)->post(route('domains.llm-api-keys.store', $domain), [
            'provider' => 'not-a-real-provider',
            'label' => 'bireysel',
            'api_key' => 'sk-test',
        ])->assertSessionHasErrors('provider');
    }

    public function test_admin_can_delete_an_api_key(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $admin->id]);
        $key = $domain->llmApiKeys()->create(['provider' => 'openai', 'label' => 'bireysel', 'api_key' => 'sk-test']);

        $response = $this->actingAs($admin)->delete(route('domains.llm-api-keys.destroy', [$domain, $key]));

        $response->assertRedirect(route('domains.show', $domain));
        $this->assertSame(0, $domain->llmApiKeys()->count());
    }

    public function test_deleting_an_api_key_belonging_to_a_different_domain_is_rejected(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $domainA = Domain::query()->create(['domain' => 'a.example.com', 'user_id' => $admin->id]);
        $domainB = Domain::query()->create(['domain' => 'b.example.com', 'user_id' => $admin->id]);
        $key = $domainB->llmApiKeys()->create(['provider' => 'openai', 'label' => 'bireysel', 'api_key' => 'sk-test']);

        $this->actingAs($admin)
            ->delete(route('domains.llm-api-keys.destroy', [$domainA, $key]))
            ->assertNotFound();

        $this->assertSame(1, $domainB->llmApiKeys()->count());
    }

    public function test_api_key_is_encrypted_at_rest(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $admin->id]);

        $domain->llmApiKeys()->create(['provider' => 'openai', 'label' => 'bireysel', 'api_key' => 'sk-plaintext']);

        $rawValue = DB::table('domain_llm_api_keys')->where('domain_id', $domain->id)->value('api_key');

        $this->assertStringNotContainsString('sk-plaintext', $rawValue);
    }

    public function test_masked_key_hides_the_middle_of_the_value(): void
    {
        $key = new DomainLlmApiKey(['api_key' => 'sk-abcdefghijklmnop']);

        $masked = $key->maskedKey();

        $this->assertStringStartsWith('sk-a', $masked);
        $this->assertStringEndsWith('mnop', $masked);
        $this->assertStringNotContainsString('efghijkl', $masked);
    }
}
