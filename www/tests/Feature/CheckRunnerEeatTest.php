<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use App\Services\CheckRunner;
use App\Services\OnPage\OnPageSeoAnalyzer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CheckRunner'in on-page analizi basariyla tamamlandiginda EeatScorer'i
 * cagirip eeat_score/eeat_breakdown'i Check satirina yazdigini, basarisiz
 * oldugunda ise ikisini de null biraktigini dogrular (bkz. CheckRunner::run()).
 */
class CheckRunnerEeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_eeat_score_and_breakdown_are_stored_when_onpage_analysis_succeeds(): void
    {
        $responses = array_fill(0, 20, new Response(200, [], '<html><body></body></html>'));
        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $check = app(CheckRunner::class)->run($keyword);

        $this->assertNull($check->onpage_error);
        $this->assertIsInt($check->eeat_score);
        $this->assertGreaterThanOrEqual(0, $check->eeat_score);
        $this->assertLessThanOrEqual(100, $check->eeat_score);

        $breakdown = $check->eeat_breakdown;
        $this->assertIsArray($breakdown);
        $this->assertSame($check->eeat_score, $breakdown['score']);
        $this->assertArrayHasKey('expertise_score', $breakdown);
        $this->assertArrayHasKey('authority_score', $breakdown);
        $this->assertArrayHasKey('trust_score', $breakdown);
        $this->assertArrayHasKey('experience_score', $breakdown);
        $this->assertEqualsCanonicalizing(
            ['author', 'published_date', 'domain_age', 'backlinks', 'https', 'security_headers', 'about_contact', 'content_depth', 'structured_data'],
            array_keys($breakdown['signals']),
        );
    }

    public function test_eeat_score_and_breakdown_are_null_when_onpage_analysis_fails(): void
    {
        $responses = array_fill(0, 20, new Response(200, [], '<html><body></body></html>'));
        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));

        $failingClient = new Client(['handler' => HandlerStack::create(new MockHandler([
            new ConnectException('baglanti basarisiz', new Request('GET', 'https://example.com')),
        ]))]);
        $this->app->instance(OnPageSeoAnalyzer::class, new OnPageSeoAnalyzer($failingClient));

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $check = app(CheckRunner::class)->run($keyword);

        $this->assertNotNull($check->onpage_error);
        $this->assertNull($check->eeat_score);
        $this->assertNull($check->eeat_breakdown);
    }

    public function test_keyword_page_renders_eeat_score_and_schema_recommendations(): void
    {
        $html = <<<'HTML'
            <html>
            <head><meta name="author" content="Ayşe Yılmaz"></head>
            <body>
                <h2>Bu nedir?</h2>
                <h2>Nasıl kullanılır?</h2>
                <a href="/hakkimizda">Hakkımızda</a>
            </body>
            </html>
            HTML;
        $responses = array_fill(0, 20, new Response(200, [], $html));
        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        app(CheckRunner::class)->run($keyword);

        $response = $this->actingAs($user)->get(route('keywords.show', $keyword));

        $response->assertOk();
        $response->assertSee('E-E-A-T');
        $response->assertSee('FAQPage');
        $response->assertSee('soru-cevap başlığı bulundu');
    }
}
