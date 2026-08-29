<?php

namespace Tests\Feature;

use App\Models\ProxyApp;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ForwardAuthLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY = '/outpost.goauthentik.io/auth/nginx';

    // A docker/private source IP — discovery only fires from a trusted subnet.
    private function forwarded(string $host, string $ip = '172.18.0.5'): array
    {
        return [
            'X-Forwarded-Host' => $host,
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Uri' => '/',
            'X-Forwarded-For' => $ip,
        ];
    }

    // --- Option B: first-contact discovery -------------------------------------

    public function test_unknown_host_is_discovered_as_pending_and_forbidden(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(self::VERIFY, $this->forwarded('newapp.example.com'))
            ->assertForbidden();

        $this->assertDatabaseHas('proxy_apps', [
            'host' => 'newapp.example.com',
            'status' => ProxyApp::STATUS_PENDING,
            'enabled' => false,
            'requested_by' => $user->id,
        ]);
    }

    public function test_repeat_traffic_does_not_duplicate_the_pending_row(): void
    {
        User::factory()->withPersonalTeam()->create();

        $this->get(self::VERIFY, $this->forwarded('dup.example.com'))->assertForbidden();
        $this->get(self::VERIFY, $this->forwarded('dup.example.com'))->assertForbidden();

        $this->assertSame(1, ProxyApp::where('host', 'dup.example.com')->count());
    }

    public function test_unknown_host_from_an_untrusted_ip_is_not_discovered(): void
    {
        // A public source IP: still 403, but no junk row is written.
        $this->get(self::VERIFY, $this->forwarded('evil.example.com', '203.0.113.9'))
            ->assertForbidden();

        $this->assertDatabaseMissing('proxy_apps', ['host' => 'evil.example.com']);
    }

    public function test_discovery_is_rate_limited_per_ip(): void
    {
        config(['forward-auth.discovery_throttle' => 2]);

        // Three fresh hosts from the same trusted IP; only the first two register.
        foreach (['a.example.com', 'b.example.com', 'c.example.com'] as $host) {
            $this->get(self::VERIFY, $this->forwarded($host))->assertForbidden();
        }

        $this->assertDatabaseHas('proxy_apps', ['host' => 'a.example.com']);
        $this->assertDatabaseHas('proxy_apps', ['host' => 'b.example.com']);
        $this->assertDatabaseMissing('proxy_apps', ['host' => 'c.example.com']);
    }

    public function test_pending_app_is_forbidden_even_for_an_entitled_user(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        ProxyApp::factory()->pending()->create(['host' => 'app.example.com', 'team_id' => $team->id]);

        $this->actingAs($team->owner)
            ->get(self::VERIFY, $this->forwarded('app.example.com'))
            ->assertForbidden();
    }

    public function test_rejected_app_is_forbidden(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        ProxyApp::factory()->rejected()->create(['host' => 'app.example.com', 'team_id' => $team->id]);

        $this->actingAs($team->owner)
            ->get(self::VERIFY, $this->forwarded('app.example.com'))
            ->assertForbidden();
    }

    // --- Option A: deploy-time machine registration ----------------------------

    public function test_machine_with_scope_can_upsert_an_app(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        Passport::actingAsClient(Client::factory()->create(), ['forward-auth']);

        $response = $this->postJson('/api/forward-auth/apps', [
            'host' => 'grafana.example.com',
            'name' => 'Grafana',
            'owner_team_id' => $team->id,
            'allow_team_ids' => [$team->id],
            'status' => ProxyApp::STATUS_APPROVED,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('proxy_apps', [
            'host' => 'grafana.example.com',
            'status' => ProxyApp::STATUS_APPROVED,
            'enabled' => true,
            'team_id' => $team->id,
        ]);
    }

    public function test_machine_registration_defaults_to_pending(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        Passport::actingAsClient(Client::factory()->create(), ['forward-auth']);

        $this->postJson('/api/forward-auth/apps', [
            'host' => 'unsure.example.com',
            'name' => 'Unsure',
            'owner_team_id' => $team->id,
        ])->assertCreated();

        $this->assertDatabaseHas('proxy_apps', [
            'host' => 'unsure.example.com',
            'status' => ProxyApp::STATUS_PENDING,
            'enabled' => false,
        ]);
    }

    public function test_redeploy_without_status_does_not_demote_a_live_app(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $app = ProxyApp::factory()->create([
            'host' => 'grafana.example.com',
            'team_id' => $team->id,
            'status' => ProxyApp::STATUS_APPROVED,
            'enabled' => true,
        ]);

        Passport::actingAsClient(Client::factory()->create(), ['forward-auth']);

        // A plain redeploy: same host + name, no status/enabled.
        $this->postJson('/api/forward-auth/apps', [
            'host' => 'grafana.example.com',
            'name' => 'Grafana (v2)',
            'owner_team_id' => $team->id,
        ])->assertOk();

        $app->refresh();
        $this->assertSame(ProxyApp::STATUS_APPROVED, $app->status);
        $this->assertTrue($app->enabled);
        $this->assertSame('Grafana (v2)', $app->name);
    }

    public function test_machine_without_the_scope_is_forbidden(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        Passport::actingAsClient(Client::factory()->create(), []);

        $this->postJson('/api/forward-auth/apps', [
            'host' => 'nope.example.com',
            'name' => 'Nope',
            'owner_team_id' => $team->id,
        ])->assertForbidden();
    }

    // --- Approval turns a discovered app on -------------------------------------

    public function test_admin_can_approve_a_pending_app_and_then_it_lets_entitled_users_in(): void
    {
        config(['auth.admin_emails' => ['admin@example.com']]);
        $admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);

        $team = Team::factory()->create(['personal_team' => false]);
        $member = $team->owner;
        $app = ProxyApp::factory()->pending()->create(['host' => 'app.example.com', 'team_id' => $team->id]);

        $this->actingAs($admin)
            ->postJson(route('admin.forward-auth.apps.approve', $app), [
                'owner_team_id' => $team->id,
                'allow_team_ids' => [$team->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('proxy_apps', [
            'id' => $app->id,
            'status' => ProxyApp::STATUS_APPROVED,
            'enabled' => true,
        ]);

        // The previously-pending app now admits an entitled user.
        $this->actingAs($member)
            ->get(self::VERIFY, $this->forwarded('app.example.com'))
            ->assertOk();
    }

    public function test_non_admin_cannot_reach_the_approval_queue(): void
    {
        config(['auth.admin_emails' => ['admin@example.com']]);
        $user = User::factory()->withPersonalTeam()->create(['email' => 'someone@example.com']);

        $this->actingAs($user)
            ->getJson(route('admin.forward-auth.apps'))
            ->assertNotFound(); // OnlyHost hides admin surface behind a 404
    }
}
