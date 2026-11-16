<?php

namespace Tests\Feature;

use App\Http\Controllers\Mobile\MobileAuthController;
use App\Models\MobileLoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    private const string STATE = 'state-abcdefghijklmnop';

    /** @return array{code: string, state: string} */
    private function callbackParams(string $location): array
    {
        $this->assertStringStartsWith(MobileAuthController::REDIRECT_URI.'?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        return $params;
    }

    public function test_meta_identifies_the_server(): void
    {
        config(['seo.google_login_enabled' => false]);

        $this->getJson(route('mobile.meta'))
            ->assertOk()
            ->assertJson(['app' => 'seo-ai-checker', 'mobile_api' => 3, 'google_login' => false]);
    }

    public function test_guest_starting_mobile_login_is_sent_to_login_and_returns_after_password_login(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com', 'approved_at' => now()]);
        $start = route('mobile.auth.start', ['state' => self::STATE]);

        $this->get($start)->assertRedirect(route('login'));

        $this->post(route('login.attempt'), ['email' => 'me@example.com', 'password' => 'password'])
            ->assertRedirect($start);

        $location = $this->get($start)->assertRedirect()->headers->get('Location');
        $params = $this->callbackParams($location);

        $this->assertSame(self::STATE, $params['state']);
        $this->assertSame(64, strlen($params['code']));
        $this->assertSame($user->id, MobileLoginToken::query()->sole()->user_id);
        $this->assertNotSame($params['code'], MobileLoginToken::query()->sole()->token_hash);
    }

    public function test_start_requires_a_valid_state(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);

        $this->actingAs($user)->get(route('mobile.auth.start'))->assertStatus(400);
        $this->actingAs($user)->get(route('mobile.auth.start', ['state' => 'short']))->assertStatus(400);
        $this->actingAs($user)->get(route('mobile.auth.start', ['state' => str_repeat('a', 20).'<x>']))->assertStatus(400);
    }

    public function test_code_logs_the_webview_in_once(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $code = MobileLoginToken::issueFor($user);

        $this->get(route('mobile.session', ['code' => $code]))->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        $this->get(route('mobile.session', ['code' => $code]))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $code = MobileLoginToken::issueFor($user);

        $this->travel(MobileLoginToken::TTL_SECONDS + 1)->seconds();

        $this->get(route('mobile.session', ['code' => $code]))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unapproved_user_still_gets_a_code_and_sees_pending_page_in_the_webview(): void
    {
        $user = User::factory()->create();

        $location = $this->actingAs($user)
            ->get(route('mobile.auth.start', ['state' => self::STATE]))
            ->headers->get('Location');
        $code = $this->callbackParams($location)['code'];

        auth()->logout();

        $this->get(route('mobile.session', ['code' => $code]))->assertRedirect('/');
        $this->get('/')->assertRedirect(route('pending-approval'));
    }

    public function test_code_switches_an_existing_webview_session_to_its_user(): void
    {
        $other = User::factory()->create(['approved_at' => now()]);
        $user = User::factory()->create(['approved_at' => now()]);
        $code = MobileLoginToken::issueFor($user);

        $this->actingAs($other)->get(route('mobile.session', ['code' => $code]));

        $this->assertAuthenticatedAs($user);
    }

    public function test_app_webview_guest_is_sent_to_login_instead_of_the_landing_page(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone) SeoAiCheckerApp/1.0'])
            ->get('/')
            ->assertRedirect(route('login'));

        // withHeaders sonraki isteklere de uygulanir; normal tarayiciyi ayrica dene.
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone) Safari/604.1'])
            ->get('/')
            ->assertOk();
    }
}
