<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainFact;
use App\Models\User;
use App\Services\Llms\LlmsTxtValidator;
use App\Support\DomainHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmsTxtGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function domainWithPages(): array
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $page = fn (string $url, ?array $onpage = null) => $domain->sitemapUrls()->create([
            'url' => $url,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'onpage_data' => $onpage,
            'onpage_checked_at' => $onpage ? now() : null,
        ]);

        $page($domain->rootUrl(), ['title' => 'Örnek Mağaza | Slogan', 'meta_description' => 'Koşu ayakkabıları satan mağaza.']);
        $page(rtrim($domain->rootUrl(), '/') . '/hakkimizda');
        $page(rtrim($domain->rootUrl(), '/') . '/blog/kosu-rehberi', ['title' => 'Koşu Rehberi', 'meta_description' => 'Başlangıç için koşu.']);
        $page(rtrim($domain->rootUrl(), '/') . '/blog/ayakkabi-secimi');
        $page(rtrim($domain->rootUrl(), '/') . '/eski', null)->update(['removed_at' => now()]);

        return [$user, $domain];
    }

    public function test_page_renders_llms_txt_draft_from_sitemap(): void
    {
        [$user, $domain] = $this->domainWithPages();

        $response = $this->actingAs($user)->get(route('domains.llms-txt', $domain));

        $response->assertOk();
        $response->assertSee('# Örnek Mağaza', false);
        $response->assertSee('> Koşu ayakkabıları satan mağaza.');
        $response->assertSee('## Blog', false);
        $response->assertSee('/blog/kosu-rehberi): Başlangıç için koşu.', false);
        $response->assertSee('[Ayakkabi Secimi]', false);
        $response->assertSee('[Hakkimizda]', false);
        $response->assertDontSee('/eski', false);
    }

    public function test_download_returns_plain_text_attachment(): void
    {
        [$user, $domain] = $this->domainWithPages();

        $response = $this->actingAs($user)->get(route('domains.llms-txt.download', $domain));

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('filename="llms.txt"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("# Örnek Mağaza\n", $response->getContent());
    }

    public function test_other_users_cannot_see_draft(): void
    {
        [, $domain] = $this->domainWithPages();
        $stranger = User::factory()->create(['approved_at' => now()]);

        $this->actingAs($stranger)->get(route('domains.llms-txt', $domain))->assertForbidden();
    }

    public function test_domain_page_shows_new_cards(): void
    {
        [$user, $domain] = $this->domainWithPages();
        $keyword = $domain->keywords()->create(['keyword' => 'koşu ayakkabısı']);
        $keyword->checks()->create(['ai_overview_present' => true, 'ai_overview_target_cited' => true]);
        $domain->promptExplorations()->create([
            'user_id' => $user->id,
            'prompt' => 'en iyi koşu ayakkabısı',
            'providers' => ['openai'],
            'results' => ['openai' => ['present' => false, 'response' => 'rival.com öneririm', 'error' => null]],
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('AI Overview istikrarı');
        $response->assertSee('Share of Model');
        $response->assertSee('rival.com');

        $this->actingAs($user)->get(route('keywords.show', $keyword))->assertOk()->assertSee('AI Overview istikrarı');
    }

    public function test_invalid_llms_txt_shows_as_issue_in_health(): void
    {
        [$user, $domain] = $this->domainWithPages();
        DomainFact::query()->create([
            'domain' => $domain->domain,
            'checked_at' => now(),
            'llms_txt' => [
                'url' => 'https://example.com/llms.txt',
                'found' => true,
                'preview' => '<!doctype html>',
                'validation' => (new LlmsTxtValidator())->validate('<!doctype html><html></html>', 'text/html', 'example.com'),
            ],
        ]);

        $health = DomainHealth::for($domain->fresh('fact'));

        $this->assertContains('llms.txt dosyası hatalı', array_column($health->issues, 'title'));
        $this->actingAs($user)->get(route('domains.llms-txt', $domain))->assertOk()->assertSee('HTML döndürüyor');
    }
}
