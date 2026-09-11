<?php

namespace Tests\Feature;

use App\Jobs\SendWeeklySeoReportJob;
use App\Mail\WeeklySeoReportMail;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendWeeklySeoReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_a_job_only_for_approved_users_with_at_least_one_domain(): void
    {
        Bus::fake();

        $withDomain = User::factory()->create(['approved_at' => now()]);
        Domain::query()->create(['domain' => 'example.com', 'user_id' => $withDomain->id]);

        $noDomain = User::factory()->create(['approved_at' => now()]);

        $notApproved = User::factory()->create(['approved_at' => null]);
        Domain::query()->create(['domain' => 'other.com', 'user_id' => $notApproved->id]);

        $this->artisan('reports:send-weekly')->assertExitCode(0);

        Bus::assertDispatched(SendWeeklySeoReportJob::class, fn ($job) => $job->userId === $withDomain->id);
        Bus::assertDispatched(SendWeeklySeoReportJob::class, 1);
    }

    public function test_job_sends_no_mail_when_no_domain_has_any_check(): void
    {
        Mail::fake();

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->keywords()->create(['keyword' => 'örnek kelime']); // hiç check yok

        (new SendWeeklySeoReportJob($user->id))->handle(app(\App\Services\Report\WeeklySeoReportBuilder::class));

        Mail::assertNothingSent();
    }

    public function test_job_sends_mail_with_domain_report_when_data_exists(): void
    {
        Mail::fake();

        $user = User::factory()->create(['approved_at' => now(), 'email' => 'sahip@example.com']);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->keywords()->create(['keyword' => 'örnek kelime'])->checks()->create(['target_position' => 3]);

        (new SendWeeklySeoReportJob($user->id))->handle(app(\App\Services\Report\WeeklySeoReportBuilder::class));

        Mail::assertSent(WeeklySeoReportMail::class, function (WeeklySeoReportMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->user->is($user)
                && count($mail->domainReports) === 1;
        });
    }
}
