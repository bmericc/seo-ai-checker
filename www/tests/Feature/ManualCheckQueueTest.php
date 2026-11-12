<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunDomainSiteCheck;
use App\Jobs\RunKeywordCheck;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ManualCheckQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_check_button_queues_job_instead_of_running_inline(): void
    {
        Queue::fake();

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'alpha.example.com', 'user_id' => $user->id]);
        $keyword = Keyword::query()->create([
            'domain_id' => $domain->id,
            'keyword' => 'alpha keyword',
            'url' => 'https://alpha.example.com/page',
        ]);

        $this->actingAs($user)
            ->post("/keywords/{$keyword->id}/check")
            ->assertRedirect(route('keywords.show', $keyword));

        Queue::assertPushed(
            RunKeywordCheck::class,
            fn (RunKeywordCheck $job) => $job->keywordId === $keyword->id && $job->scheduled === false,
        );
    }

    public function test_domain_check_button_queues_forced_site_check(): void
    {
        Queue::fake();

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'alpha.example.com', 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/domains/{$domain->id}/check")
            ->assertRedirect(route('domains.show', $domain));

        Queue::assertPushed(
            RunDomainSiteCheck::class,
            fn (RunDomainSiteCheck $job) => $job->domainId === $domain->id && $job->forceFresh === true,
        );
    }
}
