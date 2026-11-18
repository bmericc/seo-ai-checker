<?php

namespace Tests\Feature;

use App\Mcp\Servers\SeoCheckerServer;
use App\Models\User;
use App\Support\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Attributes\Version;
use ReflectionClass;
use Tests\TestCase;

class AppVersionFooterTest extends TestCase
{
    use RefreshDatabase;

    private function versionLink(): string
    {
        return 'href="'.AppVersion::releaseUrl().'"';
    }

    public function test_home_page_footer_shows_the_version(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('v'.AppVersion::VERSION)
            ->assertSee($this->versionLink(), false);
    }

    public function test_login_page_footer_shows_the_version(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('v'.AppVersion::VERSION)
            ->assertSee($this->versionLink(), false);
    }

    public function test_dashboard_footer_shows_the_version(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('v'.AppVersion::VERSION)
            ->assertSee($this->versionLink(), false);
    }

    public function test_config_and_mcp_server_use_the_same_version(): void
    {
        $this->assertSame(AppVersion::VERSION, config('app.version'));

        $attribute = (new ReflectionClass(SeoCheckerServer::class))->getAttributes(Version::class)[0];
        $this->assertSame(AppVersion::VERSION, $attribute->getArguments()[0]);
    }
}
