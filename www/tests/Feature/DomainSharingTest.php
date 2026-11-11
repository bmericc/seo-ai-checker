<?php

namespace Tests\Feature;

use App\Jobs\RunKeywordCheck;
use App\Mcp\Servers\SeoCheckerServer;
use App\Mcp\Tools\AddDomain;
use App\Mcp\Tools\AddKeyword;
use App\Mcp\Tools\GetDomain;
use App\Mcp\Tools\ListDomains;
use App\Mcp\Tools\RunKeywordCheck as RunKeywordCheckTool;
use App\Models\Domain;
use App\Models\DomainMember;
use App\Models\Keyword;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DomainSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(['approved_at' => now(), ...$attributes]);
    }

    private function share(Domain $domain, User $user, string $permission): DomainMember
    {
        return $domain->members()->create([
            'user_id' => $user->id,
            'email' => strtolower($user->email),
            'permission' => $permission,
        ]);
    }

    public function test_owner_shares_domain_by_email_and_member_sees_it_on_dashboard(): void
    {
        $owner = $this->member();
        $friend = $this->member(['email' => 'friend@example.com']);
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/domains/{$domain->id}/members", ['email' => 'Friend@Example.com', 'permission' => 'read'])
            ->assertRedirect(route('domains.show', $domain));

        $member = $domain->members()->sole();
        $this->assertSame($friend->id, $member->user_id);
        $this->assertSame('friend@example.com', $member->email);
        $this->assertSame(DomainMember::READ, $member->permission);

        $this->actingAs($friend)->get('/')->assertOk()
            ->assertSee('shared.example.com')
            ->assertSee(__('Salt okunur'));
    }

    public function test_read_member_can_view_but_not_change_anything(): void
    {
        Queue::fake();
        $owner = $this->member();
        $reader = $this->member();
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);
        $keyword = Keyword::query()->create(['domain_id' => $domain->id, 'keyword' => 'seo']);
        $this->share($domain, $reader, DomainMember::READ);

        $this->actingAs($reader)->get("/domains/{$domain->id}")->assertOk()
            ->assertDontSee(route('domains.check', $domain))
            ->assertDontSee(route('keywords.check', $keyword));
        $this->actingAs($reader)->get("/keywords/{$keyword->id}")->assertOk();
        $this->actingAs($reader)->get("/domains/{$domain->id}/lighthouse-report")->assertOk();

        $this->actingAs($reader)->post("/domains/{$domain->id}/check")->assertForbidden();
        $this->actingAs($reader)->post("/domains/{$domain->id}/keywords", ['keyword' => 'new'])->assertForbidden();
        $this->actingAs($reader)->post("/keywords/{$keyword->id}/check")->assertForbidden();
        $this->actingAs($reader)->delete("/keywords/{$keyword->id}")->assertForbidden();
        $this->actingAs($reader)->post("/domains/{$domain->id}/shares", ['sections' => ['health']])->assertForbidden();
        $this->actingAs($reader)->delete("/domains/{$domain->id}")->assertForbidden();

        $this->assertSame(1, $domain->keywords()->count());
        Queue::assertNothingPushed();
    }

    public function test_write_member_can_edit_but_not_delete_or_manage_access(): void
    {
        $owner = $this->member();
        $writer = $this->member();
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);
        $this->share($domain, $writer, DomainMember::WRITE);

        $this->actingAs($writer)->post("/domains/{$domain->id}/keywords", ['keyword' => 'yeni kelime'])->assertRedirect();
        $this->assertSame(1, $domain->keywords()->count());

        $this->actingAs($writer)->delete("/domains/{$domain->id}")->assertForbidden();
        $this->actingAs($writer)->patch("/domains/{$domain->id}/ga4-property", ['ga4_property_id' => '123'])->assertForbidden();
        $this->actingAs($writer)->post("/domains/{$domain->id}/members", ['email' => 'x@example.com', 'permission' => 'write'])->assertForbidden();
        $this->assertDatabaseHas('domains', ['id' => $domain->id]);
    }

    public function test_owner_can_change_permission_and_revoke_access(): void
    {
        $owner = $this->member();
        $friend = $this->member();
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);
        $member = $this->share($domain, $friend, DomainMember::READ);

        $this->actingAs($owner)->patch("/domains/{$domain->id}/members/{$member->id}", ['permission' => 'write'])->assertRedirect();
        $this->assertSame(DomainMember::WRITE, $member->fresh()->permission);

        $this->actingAs($owner)->delete("/domains/{$domain->id}/members/{$member->id}")->assertRedirect();
        $this->actingAs($friend)->get("/domains/{$domain->id}")->assertForbidden();
    }

    public function test_member_can_leave_but_cannot_remove_others(): void
    {
        $owner = $this->member();
        $alice = $this->member();
        $bob = $this->member();
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);
        $aliceMember = $this->share($domain, $alice, DomainMember::WRITE);
        $bobMember = $this->share($domain, $bob, DomainMember::READ);

        $this->actingAs($alice)->delete("/domains/{$domain->id}/members/{$bobMember->id}")->assertForbidden();
        $this->actingAs($alice)->delete("/domains/{$domain->id}/members/{$aliceMember->id}")->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('domain_members', ['id' => $aliceMember->id]);
        $this->assertDatabaseHas('domain_members', ['id' => $bobMember->id]);
    }

    public function test_sharing_with_the_owner_is_rejected(): void
    {
        $owner = $this->member(['email' => 'owner@example.com']);
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);

        $this->actingAs($owner)->post("/domains/{$domain->id}/members", ['email' => 'owner@example.com', 'permission' => 'read']);

        $this->assertSame(0, $domain->members()->count());
    }

    public function test_pending_invite_is_claimed_when_the_user_first_signs_in(): void
    {
        $owner = $this->member();
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);

        $this->actingAs($owner)->post("/domains/{$domain->id}/members", ['email' => 'newbie@example.com', 'permission' => 'write']);
        $this->assertNull($domain->members()->sole()->user_id);

        $newbie = $this->member(['email' => 'newbie@example.com']);
        DomainMember::claimPendingFor($newbie);

        $this->assertSame($newbie->id, $domain->members()->sole()->user_id);
        $this->assertTrue($domain->fresh()->canBeEditedBy($newbie));
    }

    public function test_adding_a_domain_already_shared_with_you_opens_the_shared_one(): void
    {
        $owner = $this->member();
        $friend = $this->member();
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);
        $this->share($domain, $friend, DomainMember::READ);

        $this->actingAs($friend)->post('/domains', ['domain' => 'https://shared.example.com/'])
            ->assertRedirect(route('domains.show', $domain));

        $this->assertSame(0, $friend->domains()->count());
    }

    public function test_mcp_tools_respect_shared_permissions(): void
    {
        Queue::fake();
        $owner = $this->member();
        $reader = $this->member();
        $writer = $this->member();
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);
        $keyword = Keyword::query()->create(['domain_id' => $domain->id, 'keyword' => 'seo']);
        $this->share($domain, $reader, DomainMember::READ);
        $this->share($domain, $writer, DomainMember::WRITE);

        Passport::actingAs($reader, ['read', 'write']);
        SeoCheckerServer::tool(ListDomains::class)->assertOk()->assertSee(['shared.example.com', '"access":"read"']);
        SeoCheckerServer::tool(GetDomain::class, ['domain' => 'shared.example.com'])->assertOk();
        SeoCheckerServer::tool(AddKeyword::class, ['domain' => 'shared.example.com', 'keyword' => 'x'])->assertHasErrors();
        SeoCheckerServer::tool(RunKeywordCheckTool::class, ['keyword_id' => $keyword->id])->assertHasErrors();
        SeoCheckerServer::tool(AddDomain::class, ['domain' => 'shared.example.com'])->assertOk()->assertSee('"created":false');
        $this->assertSame(0, $reader->domains()->count());
        Queue::assertNothingPushed();

        Passport::actingAs($writer, ['read', 'write']);
        SeoCheckerServer::tool(AddKeyword::class, ['domain' => 'shared.example.com', 'keyword' => 'x'])->assertOk();
        SeoCheckerServer::tool(RunKeywordCheckTool::class, ['keyword_id' => $keyword->id])->assertOk();
        Queue::assertPushed(RunKeywordCheck::class);
    }
}
