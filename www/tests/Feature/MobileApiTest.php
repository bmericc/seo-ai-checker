<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MobileLoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function approvedUser(array $attributes = []): User
    {
        return User::factory()->create(['approved_at' => now(), ...$attributes]);
    }

    public function test_code_is_exchanged_once_for_a_read_only_token(): void
    {
        Artisan::call('passport:keys', ['--force' => true]);

        $user = $this->approvedUser();
        $code = MobileLoginToken::issueFor($user);

        $response = $this->postJson(route('api.v1.auth.token'), ['code' => $code])
            ->assertCreated()
            ->assertJsonPath('user.id', $user->id);

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertSame(['read'], $user->tokens()->sole()->scopes);

        $this->postJson(route('api.v1.auth.token'), ['code' => $code])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_code');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        Artisan::call('passport:keys', ['--force' => true]);

        $user = $this->approvedUser();
        $token = $this->postJson(route('api.v1.auth.token'), ['code' => MobileLoginToken::issueFor($user)])->json('token');

        $this->withToken($token)->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('user.email', $user->email);
        $this->withToken($token)->deleteJson(route('api.v1.auth.logout'))->assertNoContent();

        $this->assertTrue($user->tokens()->sole()->revoked);
    }

    public function test_api_requires_a_token(): void
    {
        $this->getJson(route('api.v1.domains.index'))->assertUnauthorized();
    }

    public function test_unapproved_user_gets_pending_error(): void
    {
        Passport::actingAs(User::factory()->create(), ['read']);

        $this->getJson(route('api.v1.domains.index'))
            ->assertForbidden()
            ->assertJsonPath('error', 'pending');
    }

    public function test_domains_lists_only_accessible_domains_with_health(): void
    {
        $user = $this->approvedUser();
        $other = $this->approvedUser();
        $mine = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        Domain::query()->create(['domain' => 'other.com', 'user_id' => $other->id]);
        $mine->keywords()->create(['keyword' => 'koşu ayakkabısı']);

        Passport::actingAs($user, ['read']);

        $this->getJson(route('api.v1.domains.index'))
            ->assertOk()
            ->assertJsonCount(1, 'domains')
            ->assertJsonPath('domains.0.domain', 'example.com')
            ->assertJsonPath('domains.0.access', 'owner')
            ->assertJsonPath('domains.0.keyword_count', 1)
            ->assertJsonPath('domains.0.score', null);
    }

    public function test_screen_returns_sections_and_hides_other_users_domains(): void
    {
        $user = $this->approvedUser();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $foreign = Domain::query()->create(['domain' => 'other.com', 'user_id' => $this->approvedUser()->id]);

        $cited = $domain->keywords()->create(['keyword' => 'koşu ayakkabısı']);
        $cited->checks()->create(['ai_overview_present' => true, 'ai_overview_target_cited' => true, 'target_position' => 3]);
        $domain->keywords()->create(['keyword' => 'trail ayakkabı'])->checks()->create(['target_position' => 9]);
        $domain->promptExplorations()->create([
            'user_id' => $user->id,
            'prompt' => 'en iyi koşu ayakkabısı',
            'providers' => ['openai', 'gemini'],
            'results' => ['openai' => ['present' => true, 'error' => null], 'gemini' => ['present' => false, 'error' => null]],
            'completed_at' => now(),
        ]);

        Passport::actingAs($user, ['read']);

        $response = $this->getJson(route('api.v1.domains.screen', $domain))->assertOk();
        $sections = collect($response->json('sections'))->keyBy('type');

        // Site henuz kontrol edilmedi: skor yerine bilgi kutusu.
        $this->assertSame('notice', $response->json('sections.0.type'));
        $this->assertSame(['Kelime', 'Ort. Google sırası', 'AI Overview atfı'], array_column($sections['metrics']['data']['items'], 'label'));
        $this->assertSame('6,0', $sections['metrics']['data']['items'][1]['value']);
        $this->assertSame(['mentioned', 'absent'], array_column($sections['assistants']['data']['items'], 'status'));
        $this->assertSame(['koşu ayakkabısı', 'trail ayakkabı'], array_column($sections['table']['data']['rows'], 'title'));
        $this->assertSame('AIO kaynak', $sections['table']['data']['rows'][0]['badges'][0]['label']);

        $this->getJson(route('api.v1.domains.screen', $foreign))->assertNotFound();
    }

    public function test_changes_are_merged_across_domains_and_localized(): void
    {
        $user = $this->approvedUser(['locale' => 'tr']);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'seramik kupa']);

        $this->travel(-40)->days();
        $keyword->checks()->create(['target_position' => 3, 'ai_overview_present' => true, 'ai_overview_target_cited' => true]);
        $this->travelBack();
        $keyword->checks()->create(['target_position' => 7, 'ai_overview_present' => true, 'ai_overview_target_cited' => false]);

        Passport::actingAs($user, ['read']);

        $items = $this->getJson(route('api.v1.changes', ['days' => 30]))
            ->assertOk()
            ->assertJsonPath('days', 30)
            ->json('items');

        $this->assertSame([
            '“seramik kupa” için AI Overview artık sizi kaynak göstermiyor',
            '“seramik kupa” 3. sıradan 7. sıraya düştü',
        ], array_column($items, 'title'));
        $this->assertSame('loss', $items[0]['direction']);
        $this->assertSame('example.com', $items[0]['domain']);

        // Desteklenmeyen aralik varsayilana (7 gun) doner.
        $this->getJson(route('api.v1.changes', ['days' => 365]))->assertJsonPath('days', 7);
    }

    public function test_every_domain_tab_renders_and_unknown_tab_falls_back_to_overview(): void
    {
        $user = $this->approvedUser();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->keywords()->create(['keyword' => 'koşu ayakkabısı'])->checks()->create(['target_position' => 4, 'ai_overview_present' => true]);

        Passport::actingAs($user, ['read']);

        $overview = $this->getJson(route('api.v1.domains.screen', $domain))->assertOk();
        $keys = array_column($overview->json('tabs'), 'key');

        // Site haritasinda sayfa yokken "Sayfalar" sekmesi listelenmez.
        $this->assertNotContains('pages', $keys);
        $this->assertSame(['overview', 'keywords', 'health', 'ai', 'indexnow', 'competitors', 'backlinks', 'history', 'info'], $keys);
        $this->assertSame(1, $overview->json('tabs.1.badge'));

        foreach ($keys as $key) {
            $response = $this->getJson(route('api.v1.domains.screen', ['domain' => $domain, 'tab' => $key]))->assertOk();
            $this->assertSame($key, $response->json('tab'), $key);
            $this->assertNotEmpty($response->json('sections'), $key);
            foreach ($response->json('sections') as $section) {
                $this->assertIsString($section['type'], $key);
                $this->assertIsString($section['title'], $key);
            }
        }

        $keywords = $this->getJson(route('api.v1.domains.screen', ['domain' => $domain, 'tab' => 'keywords']));
        $this->assertSame('table', $keywords->json('sections.0.type'));
        $this->assertSame('4', $keywords->json('sections.0.data.rows.0.value'));
        $this->assertStringEndsWith('#tab-keywords', $keywords->json('web_url'));

        $info = $this->getJson(route('api.v1.domains.screen', ['domain' => $domain, 'tab' => 'info']));
        $this->assertSame('kv', $info->json('sections.0.type'));
        $this->assertSame('example.com', $info->json('sections.0.data.items.0.value'));

        $this->getJson(route('api.v1.domains.screen', ['domain' => $domain, 'tab' => 'pages']))->assertJsonPath('tab', 'overview');
        $this->getJson(route('api.v1.domains.screen', ['domain' => $domain, 'tab' => 'nope']))->assertJsonPath('tab', 'overview');
    }

    public function test_tab_labels_follow_the_user_locale(): void
    {
        $user = $this->approvedUser(['locale' => 'en']);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        Passport::actingAs($user, ['read']);

        $response = $this->getJson(route('api.v1.domains.screen', ['domain' => $domain, 'tab' => 'info']))->assertOk();
        $this->assertSame('Overview', $response->json('tabs.0.label'));
        $this->assertSame('Domain info', $response->json('sections.0.title'));
        $this->assertSame(['Domain', 'Owner', 'Added'], array_slice(array_column($response->json('sections.0.data.items'), 'label'), 0, 3));
    }

    public function test_english_user_gets_english_texts(): void
    {
        $user = $this->approvedUser(['locale' => 'en']);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'ceramic mug']);

        $this->travel(-40)->days();
        $keyword->checks()->create(['target_position' => 3]);
        $this->travelBack();
        $keyword->checks()->create(['target_position' => 7]);

        Passport::actingAs($user, ['read']);

        $this->getJson(route('api.v1.changes', ['days' => 30]))
            ->assertJsonPath('items.0.title', '“ceramic mug” dropped from position 3 to 7');
    }

    public function test_web_bridge_opens_a_web_session_and_only_redirects_within_the_site(): void
    {
        $user = $this->approvedUser();

        Passport::actingAs($user, ['read']);

        $this->get(route('mobile.web', ['to' => '/domains/5']))->assertRedirect('/domains/5');
        $this->assertAuthenticatedAs($user, 'web');

        $this->get(route('mobile.web', ['to' => '//evil.example/x']))->assertRedirect('/');
        $this->get(route('mobile.web', ['to' => 'https://evil.example']))->assertRedirect('/');
    }
}
