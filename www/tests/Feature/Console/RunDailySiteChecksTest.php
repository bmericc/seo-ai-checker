<?php

namespace Tests\Feature\Console;

use App\Jobs\RunDomainSiteCheck;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunDailySiteChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_a_site_check_job_for_every_domain(): void
    {
        Queue::fake();

        $user = User::factory()->create(['approved_at' => now()]);
        $first = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $second = Domain::query()->create(['domain' => 'example.org', 'user_id' => $user->id]);

        Artisan::call('domains:run-daily-checks');

        Queue::assertPushed(RunDomainSiteCheck::class, 2);
        Queue::assertPushed(RunDomainSiteCheck::class, fn (RunDomainSiteCheck $job) => $job->domainId === $first->id);
        Queue::assertPushed(RunDomainSiteCheck::class, fn (RunDomainSiteCheck $job) => $job->domainId === $second->id);
    }

    public function test_it_dispatches_nothing_when_there_are_no_domains(): void
    {
        Queue::fake();

        Artisan::call('domains:run-daily-checks');

        Queue::assertNothingPushed();
    }

    public function test_the_command_is_scheduled_to_run_daily(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('domains:run-daily-checks', Artisan::output());
    }
}
