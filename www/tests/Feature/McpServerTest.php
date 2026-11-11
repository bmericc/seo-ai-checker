<?php

namespace Tests\Feature;

use App\Jobs\RunDomainSiteCheck;
use App\Jobs\RunKeywordCheck;
use App\Mcp\Servers\SeoCheckerServer;
use App\Mcp\Tools\AddDomain;
use App\Mcp\Tools\AddKeyword;
use App\Mcp\Tools\CheckAiAnswer;
use App\Mcp\Tools\CreateReportLink;
use App\Mcp\Tools\ExplorePrompt;
use App\Mcp\Tools\GetAiCheckPrompts;
use App\Mcp\Tools\GetDomain;
use App\Mcp\Tools\GetPromptExploration;
use App\Mcp\Tools\ListAiChecks;
use App\Mcp\Tools\ListDomains;
use App\Mcp\Tools\ListKeywords;
use App\Mcp\Tools\RunDomainCheck;
use App\Mcp\Tools\RunKeywordCheck as RunKeywordCheckTool;
use App\Mcp\Tools\SubmitIndexNow;
use App\Models\Domain;
use App\Models\PromptExploration;
use App\Models\ReportShare;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): User
    {
        return User::factory()->create(['approved_at' => now(), ...$attributes]);
    }

    private function domainFor(User $user, string $name = 'example.com'): Domain
    {
        return Domain::query()->create(['domain' => $name, 'user_id' => $user->id]);
    }

    public function test_mcp_endpoint_requires_a_bearer_token_and_points_to_discovery(): void
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], [
            'Accept' => 'application/json, text/event-stream',
        ]);

        $response->assertStatus(401);
        $this->assertStringContainsString(
            '/.well-known/oauth-protected-resource/mcp',
            (string) $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_site_root_is_not_an_mcp_address(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource')->assertNotFound();

        $this->postJson('/', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'], [
            'Accept' => 'application/json, text/event-stream',
        ])->assertNotFound()->assertJsonPath('id', 1)->assertJsonPath('error.code', -32601);

        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk();
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk();
        $this->get('/')->assertOk();
    }

    public function test_discovery_metadata_advertises_read_and_write_scopes(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('scopes_supported', ['read', 'write']);

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('scopes_supported', ['read', 'write'])
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'));
    }

    public function test_guest_on_the_authorize_page_is_sent_straight_to_google_login(): void
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: 'Claude',
            redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
            confidential: false,
        );

        $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'read',
            'state' => 'abc',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))->assertRedirect(route('google.redirect'));

        // Google girisinden sonra onay sayfasina geri donulur.
        $this->assertStringContainsString('/oauth/authorize?', (string) session('url.intended'));
    }

    public function test_full_oauth_flow_issues_a_token_that_can_call_the_server(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $user = $this->member();
        $this->domainFor($user);
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: 'Claude',
            redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
            confidential: false,
        );

        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $consent = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'read write',
            'state' => 'abc',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]));
        $consent->assertOk()->assertSee('Claude')->assertSee(__('İzin ver'));

        $authToken = session('authToken');
        $approve = $this->post('/oauth/authorize', [
            'state' => 'abc',
            'client_id' => $client->getKey(),
            'auth_token' => $authToken,
        ]);
        $location = (string) $approve->headers->get('Location');
        $this->assertStringStartsWith('https://claude.ai/api/mcp/auth_callback?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $token = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'code_verifier' => $verifier,
            'code' => $query['code'],
        ])->assertOk()->json('access_token');

        $this->app['auth']->forgetGuards();

        $list = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], [
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => 'Bearer '.$token,
        ]);
        $list->assertOk();
        $names = collect($list->json('result.tools'))->pluck('name');
        $this->assertTrue($names->contains('list_domains'));
        $this->assertTrue($names->contains('add_keyword'));
        $this->assertFalse($names->contains('explore_prompt'), 'explore_prompt is admin only');
    }

    public function test_read_only_token_does_not_see_or_run_write_tools(): void
    {
        $user = $this->member();
        $this->domainFor($user);
        Passport::actingAs($user, ['read']);

        SeoCheckerServer::tools()
            ->assertRegistered([ListDomains::class, GetDomain::class, ListKeywords::class])
            ->assertNotRegistered([AddDomain::class, AddKeyword::class, SubmitIndexNow::class, ExplorePrompt::class]);
    }

    public function test_packages_default_mcp_scope_counts_as_read_only(): void
    {
        $user = $this->member();
        Passport::actingAs($user, ['mcp:use']);

        SeoCheckerServer::tools()
            ->assertRegistered(ListDomains::class)
            ->assertNotRegistered(AddDomain::class);
    }

    public function test_member_sees_only_own_domains_and_admin_sees_all(): void
    {
        $alice = $this->member();
        $bob = $this->member();
        $this->domainFor($alice, 'alice.com');
        $this->domainFor($bob, 'bob.com');

        Passport::actingAs($alice, ['read']);
        SeoCheckerServer::tool(ListDomains::class)->assertOk()->assertSee('alice.com')->assertDontSee('bob.com');
        SeoCheckerServer::tool(GetDomain::class, ['domain' => 'bob.com'])->assertHasErrors();

        $admin = $this->member(['is_admin' => true]);
        Passport::actingAs($admin, ['read']);
        SeoCheckerServer::tool(ListDomains::class)->assertOk()->assertSee(['alice.com', 'bob.com']);
        SeoCheckerServer::tool(GetDomain::class, ['domain' => 'bob.com'])->assertOk()->assertSee('bob.com');
    }

    public function test_unapproved_user_gets_no_tools(): void
    {
        $user = User::factory()->create(['approved_at' => null]);
        Passport::actingAs($user, ['read', 'write']);

        SeoCheckerServer::tools()->assertNotRegistered([ListDomains::class, AddDomain::class]);
    }

    public function test_add_domain_and_keyword_and_queue_checks(): void
    {
        Queue::fake();
        $user = $this->member();
        Passport::actingAs($user, ['read', 'write']);

        SeoCheckerServer::tool(AddDomain::class, ['domain' => 'https://www.Example.com/page'])
            ->assertOk()->assertSee('example.com');
        $domain = $user->domains()->sole();
        $this->assertSame('example.com', $domain->domain);
        Queue::assertPushed(RunDomainSiteCheck::class, fn ($job) => $job->domainId === $domain->id);

        SeoCheckerServer::tool(AddDomain::class, ['domain' => 'example.com'])->assertOk()->assertSee(__(':domain zaten kayıtlı.', ['domain' => 'example.com']));
        $this->assertSame(1, $user->domains()->count());

        SeoCheckerServer::tool(AddKeyword::class, ['domain' => 'example.com', 'keyword' => '  koşu ayakkabısı '])
            ->assertOk();
        $keyword = $domain->keywords()->sole();
        $this->assertSame('koşu ayakkabısı', $keyword->keyword);

        SeoCheckerServer::tool(RunKeywordCheckTool::class, ['keyword_id' => $keyword->id])->assertOk();
        Queue::assertPushed(RunKeywordCheck::class, fn ($job) => $job->keywordId === $keyword->id);

        SeoCheckerServer::tool(RunDomainCheck::class, ['domain' => (string) $domain->id])->assertOk();
        Queue::assertPushed(RunDomainSiteCheck::class, fn ($job) => $job->domainId === $domain->id && $job->forceFresh);

        SeoCheckerServer::tool(ListKeywords::class, ['domain' => 'example.com'])->assertOk()->assertSee('koşu ayakkabısı');
    }

    public function test_member_cannot_touch_someone_elses_keyword(): void
    {
        Queue::fake();
        $owner = $this->member();
        $keyword = $this->domainFor($owner)->keywords()->create(['keyword' => 'secret']);

        Passport::actingAs($this->member(), ['read', 'write']);
        SeoCheckerServer::tool(RunKeywordCheckTool::class, ['keyword_id' => $keyword->id])->assertHasErrors();
        Queue::assertNothingPushed();
    }

    public function test_create_report_link(): void
    {
        $user = $this->member();
        $domain = $this->domainFor($user);
        Passport::actingAs($user, ['read', 'write']);

        SeoCheckerServer::tool(CreateReportLink::class, [
            'domain' => 'example.com',
            'sections' => ['summary', 'keywords'],
            'expires_in_days' => 0,
        ])->assertOk()->assertSee('/r/');

        $share = ReportShare::query()->sole();
        $this->assertSame($domain->id, $share->domain_id);
        $this->assertSame(['summary', 'keywords'], $share->sections);
        $this->assertNull($share->expires_at);
    }

    public function test_indexnow_submit_needs_a_key_first(): void
    {
        $user = $this->member();
        $this->domainFor($user);
        Passport::actingAs($user, ['read', 'write']);

        SeoCheckerServer::tool(SubmitIndexNow::class, ['domain' => 'example.com', 'urls' => ['https://example.com/a']])
            ->assertHasErrors();
    }

    public function test_prompt_explorer_tools_are_admin_only(): void
    {
        Passport::actingAs($this->member(), ['read', 'write']);
        SeoCheckerServer::tools()->assertNotRegistered([ExplorePrompt::class]);
        SeoCheckerServer::tools()->assertRegistered([GetPromptExploration::class, ListAiChecks::class, CheckAiAnswer::class]);

        Passport::actingAs($this->member(['is_admin' => true]), ['read', 'write']);
        SeoCheckerServer::tools()->assertRegistered([ExplorePrompt::class, GetPromptExploration::class]);
    }

    private function manualCheck(array $args): \Laravel\Mcp\Server\Testing\TestResponse
    {
        return SeoCheckerServer::tool(CheckAiAnswer::class, ['domain' => 'example.com', 'assistant' => 'anthropic', 'model' => 'test-model', ...$args]);
    }

    public function test_check_ai_answer_uses_system_prompt_format_and_saves_manual_result(): void
    {
        $user = $this->member();
        $domain = $this->domainFor($user);
        $keyword = $domain->keywords()->create(['keyword' => 'Muhasebe Programı']);
        Passport::actingAs($user, ['read', 'write']);

        $this->manualCheck([
            'keyword' => '  muhasebe   programı ',
            'answer' => 'Try rival.io.',
            'follow_up_answer' => 'In Turkey: Example.com',
            'model' => 'claude-opus-5-5',
        ])->assertOk()->assertSee('"mentions_domain":false')->assertSee('"mentioned_in_follow_up":true')
            ->assertSee('Muhasebe Programı konusunda hangi web sitelerine bakmalıyım?')->assertSee('rival.io');

        $exploration = PromptExploration::query()->sole();
        $result = $exploration->resultFor('anthropic');
        $this->assertSame($keyword->id, $exploration->keyword_id);
        $this->assertSame('Muhasebe Programı konusunda hangi web sitelerine bakmalıyım?', $exploration->prompt);
        $this->assertSame('manual', $result['source']);
        $this->assertTrue($result['follow_up_present']);
        $this->assertSame(\App\Services\Llm\LlmVisibilityPrompt::FOLLOW_UP_PROMPT, $result['follow_up_prompt']);
        $this->assertSame(1, $domain->keywords()->count());
    }

    public function test_check_ai_answer_counts_cited_urls_and_reports_absence(): void
    {
        $user = $this->member();
        $this->domainFor($user)->keywords()->create(['keyword' => 'muhasebe']);
        Passport::actingAs($user, ['read', 'write']);

        $this->manualCheck(['keyword' => 'muhasebe', 'answer' => 'Rival is popular.', 'assistant' => 'openai',
            'citations' => ['https://blog.example.com/post', 'https://rival.io']])
            ->assertOk()->assertSee('"mentions_domain":true')->assertSee('"mentioned_in_text":false');

        $this->manualCheck(['keyword' => 'muhasebe', 'answer' => 'Rival is popular.', 'assistant' => 'other',
            'citations' => ['https://notexample.com/x']])
            ->assertOk()->assertSee('"mentions_domain":false');
    }

    public function test_check_ai_answer_rejects_free_prompts_and_unknown_keywords(): void
    {
        $user = $this->member();
        $domain = $this->domainFor($user);
        Passport::actingAs($user, ['read', 'write']);

        $this->manualCheck(['prompt' => 'Best accounting software?', 'answer' => 'example.com'])->assertHasErrors();
        $domain->keywords()->create(['keyword' => 'muhasebe']);
        SeoCheckerServer::tool(CheckAiAnswer::class, ['domain' => 'example.com', 'assistant' => 'grok', 'keyword' => 'muhasebe', 'answer' => 'x'])->assertHasErrors();
        $this->manualCheck(['keyword' => 'muhasebe', 'answer' => 'x', 'model' => '   '])->assertHasErrors();
        $domain->keywords()->where('keyword', 'muhasebe')->delete();
        $this->manualCheck(['keyword' => 'e-fatura', 'answer' => 'example.com'])->assertHasErrors(['get_ai_check_prompts']);

        $this->assertSame(0, PromptExploration::query()->count());
        $this->assertSame(0, $domain->keywords()->count());
    }

    public function test_check_ai_answer_needs_write_scope_and_own_domain(): void
    {
        $owner = $this->member();
        $this->domainFor($owner)->keywords()->create(['keyword' => 'muhasebe']);

        Passport::actingAs($this->member(), ['read']);
        SeoCheckerServer::tools()->assertNotRegistered([CheckAiAnswer::class]);

        Passport::actingAs($this->member(), ['read', 'write']);
        $this->manualCheck(['keyword' => 'muhasebe', 'answer' => 'example.com'])->assertHasErrors();

        $this->assertSame(0, PromptExploration::query()->count());
    }

    public function test_get_ai_check_prompts_adds_a_new_keyword_first(): void
    {
        $user = $this->member();
        $domain = $this->domainFor($user);
        $domain->keywords()->create(['keyword' => 'muhasebe']);

        Passport::actingAs($user, ['read']);
        SeoCheckerServer::tool(GetAiCheckPrompts::class, ['domain' => 'example.com', 'keyword' => 'e-fatura'])->assertHasErrors(['write']);
        SeoCheckerServer::tool(GetAiCheckPrompts::class, ['domain' => 'example.com', 'keyword' => 'MUHASEBE'])
            ->assertOk()->assertSee('"keyword_created":false')->assertSee('"count":1');

        Passport::actingAs($user, ['read', 'write']);
        SeoCheckerServer::tool(GetAiCheckPrompts::class, ['domain' => 'example.com', 'keyword' => 'e-fatura'])
            ->assertOk()->assertSee('"keyword_created":true')->assertSee('e-fatura konusunda hangi web sitelerine bakmalıyım?')
            ->assertSee('"system_instruction"')->assertSee('"follow_up_prompt"');
        $this->assertSame(2, $domain->keywords()->count());

        $this->manualCheck(['keyword' => 'e-fatura', 'answer' => 'example.com', 'assistant' => 'Grok', 'model' => 'grok-5'])
            ->assertOk()->assertSee('"assistant":"grok"')->assertSee('"assistant_label":"Grok"')->assertSee('"model":"grok-5"');
        $this->manualCheck(['keyword' => 'muhasebe', 'answer' => 'none', 'assistant' => 'ChatGPT'])
            ->assertOk()->assertSee('"assistant":"openai"');

        SeoCheckerServer::tool(GetAiCheckPrompts::class, ['domain' => 'example.com', 'assistant' => 'grok'])
            ->assertOk()->assertSee('"count":2')->assertSee('"last_mentioned":true')->assertSee('"last_model":"grok-5"');

        SeoCheckerServer::tool(ListAiChecks::class, ['domain' => 'example.com', 'assistant' => 'grok'])
            ->assertOk()->assertSee('"count":1')->assertSee('"keyword":"e-fatura"')->assertSee('"model":"grok-5"')->assertSee('"label":"Grok"');
        SeoCheckerServer::tool(ListAiChecks::class, ['domain' => 'example.com', 'keyword' => 'Muhasebe'])
            ->assertOk()->assertSee('"count":1');
    }

    public function test_manual_checks_are_kept_as_history_for_members(): void
    {
        $user = $this->member();
        $domain = $this->domainFor($user);
        $keyword = $domain->keywords()->create(['keyword' => 'muhasebe']);
        Passport::actingAs($user, ['read', 'write']);

        $this->manualCheck(['keyword' => 'muhasebe', 'answer' => 'Rival only.'])->assertOk();
        $this->manualCheck(['keyword' => 'muhasebe', 'answer' => 'Now example.com too.', 'assistant' => 'grok'])->assertOk();
        $domain->promptExplorations()->create([
            'user_id' => $user->id, 'prompt' => 'Other question', 'providers' => ['openai'],
            'results' => ['openai' => ['present' => false, 'response' => 'x', 'error' => null]], 'completed_at' => now(),
        ]);

        SeoCheckerServer::tool(ListAiChecks::class, ['domain' => 'example.com', 'prompt_contains' => 'muhasebe'])
            ->assertOk()->assertSee('"count":2')->assertSee('"mentioned_count":1')->assertSee('"not_mentioned"');
        SeoCheckerServer::tool(ListAiChecks::class, ['domain' => 'example.com', 'source' => 'api'])
            ->assertOk()->assertSee('"count":1')->assertSee('Other question');

        $exploration = $keyword->aiChecks()->first();
        SeoCheckerServer::tool(GetPromptExploration::class, ['exploration_id' => $exploration->id])
            ->assertOk()->assertSee('"source":"manual"');

        $this->actingAs($user)->get(route('domains.show', $domain))->assertOk()->assertSee('muhasebe konusunda');
        $this->actingAs($user)->get(route('domains.prompts.show', [$domain, $exploration]))->assertOk()->assertSee('Manuel (MCP)');
        $this->actingAs($user)->get(route('keywords.show', $keyword))->assertOk()->assertSee('Grok');
        $this->withoutMiddleware(PreventRequestForgery::class)->actingAs($user)->post(route('domains.prompts.store', $domain), ['prompt' => 'x'])->assertForbidden();
    }

    public function test_user_can_revoke_an_mcp_app_from_connections(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $user = $this->member();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: 'Claude',
            redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
            confidential: false,
        );
        $token = $user->tokens()->create([
            'id' => Str::random(40),
            'client_id' => $client->getKey(),
            'scopes' => ['read'],
            'revoked' => false,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->get('/connections')->assertOk()->assertSee('Claude')->assertSee(url('/mcp'));

        $this->delete(route('connections.mcp.destroy', $client->getKey()))->assertRedirect(route('connections'));
        $this->assertTrue($token->fresh()->revoked);
    }
}
