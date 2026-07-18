<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordSuggestionDismissalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_dismissing_a_suggestion_hides_it_but_keeps_the_others(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->domainChecks()->create([
            'suggested_keywords' => [
                ['phrase' => 'unwanted phrase', 'score' => 10.0],
                ['phrase' => 'good phrase', 'score' => 8.0],
            ],
        ]);

        $response = $this->actingAs($user)->post(
            route('domains.keyword-suggestions.dismiss', $domain),
            ['phrase' => 'unwanted phrase']
        );

        $response->assertRedirect(route('domains.show', $domain));

        $page = $this->actingAs($user)->get(route('domains.show', $domain));
        $page->assertDontSee('unwanted phrase');
        $page->assertSee('good phrase');
    }

    public function test_dismissed_suggestion_is_not_regenerated_on_the_next_check(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $domain->dismissKeywordSuggestion('Unwanted Phrase');

        $this->assertSame(['unwanted phrase'], $domain->fresh()->dismissed_keyword_suggestions);
    }

    public function test_dismissing_the_same_phrase_twice_does_not_duplicate_it(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);

        $domain->dismissKeywordSuggestion('phrase');
        $domain->dismissKeywordSuggestion('phrase');
        $domain->dismissKeywordSuggestion('PHRASE');

        $this->assertSame(['phrase'], $domain->fresh()->dismissed_keyword_suggestions);
    }

    public function test_non_admin_cannot_dismiss_suggestions_on_another_users_domain(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $otherUser = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $otherUser->id]);

        $this->actingAs($user)
            ->post(route('domains.keyword-suggestions.dismiss', $domain), ['phrase' => 'x'])
            ->assertStatus(403);
    }
}
