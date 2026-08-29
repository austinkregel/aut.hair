<?php

namespace Tests\Feature;

use App\Models\ProxyApp;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ForwardAuthLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY = '/outpost.goauthentik.io/auth/nginx';

    private function forwarded(string $host): array
    {
        return [
            'X-Forwarded-Host' => $host,
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Uri' => '/',
        ];
    }

    /**
     * Hit verify simulating the real socket peer via REMOTE_ADDR — the value the
     * discovery gate/rate-limit key off (NOT the spoofable X-Forwarded-For).
     * Defaults to a docker/private IP so discovery is allowed.
     */
    private function verifyFrom(string $host, string $remoteAddr = '172.18.0.5')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
            ->get(self::VERIFY, $this->forwarded($host));
    }

    // --- Option B: first-contact discovery -------------------------------------

    public function test_unknown_host_is_discovered_as_pending_and_forbidden(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user);
        $this->verifyFrom('newapp.example.com')->assertForbidden();

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

        $this->verifyFrom('dup.example.com')->assertForbidden();
        $this->verifyFrom('dup.example.com')->assertForbidden();

        $this->assertSame(1, ProxyApp::where('host', 'dup.example.com')->count());
    }

    public function test_unknown_host_from_an_untrusted_ip_is_not_discovered(): void
    {
        // A public socket peer: still 403, but no junk row is written. An attacker
        // cannot forge REMOTE_ADDR, so spoofing X-Forwarded-For does not help.
        $this->verifyFrom('evil.example.com', '203.0.113.9')->assertForbidden();

        $this->assertDatabaseMissing('proxy_apps', ['host' => 'evil.example.com']);
    }

    public function test_spoofed_x_forwarded_for_cannot_pass_the_discovery_gate(): void
    {
        // Public socket peer, but a spoofed private X-Forwarded-For. The gate keys on
        // REMOTE_ADDR, so the spoof is ignored and no row is created.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get(self::VERIFY, $this->forwarded('spoof.example.com') + ['X-Forwarded-For' => '10.0.0.5'])
            ->assertForbidden();

        $this->assertDatabaseMissing('proxy_apps', ['host' => 'spoof.example.com']);
    }

    public function test_discovery_is_rate_limited_per_ip(): void
    {
        config(['forward-auth.discovery_throttle' => 2]);

        // Three fresh hosts from the same trusted peer; only the first two register.
        foreach (['a.example.com', 'b.example.com', 'c.example.com'] as $host) {
            $this->verifyFrom($host)->assertForbidden();
        }

        $this->assertDatabaseHas('proxy_apps', ['host' => 'a.example.com']);
        $this->assertDatabaseHas('proxy_apps', ['host' => 'b.example.com']);
        $this->assertDatabaseMissing('proxy_apps', ['host' => 'c.example.com']);
    }

    public function test_shared_secret_gate_rejects_requests_without_the_secret(): void
    {
        config(['forward-auth.shared_secret' => 's3cret']);
        $team = Team::factory()->create(['personal_team' => false]);
        ProxyApp::factory()->create(['host' => 'app.example.com', 'team_id' => $team->id]);
        $this->actingAs($team->owner);

        // Missing secret → rejected before any other logic. (Runs first: test-client
        // default headers persist across requests, so set the header only at the end.)
        $this->get(self::VERIFY, $this->forwarded('app.example.com'))->assertForbidden();

        // Wrong secret → rejected.
        $this->withHeaders(['X-Forward-Auth-Secret' => 'wrong'])
            ->get(self::VERIFY, $this->forwarded('app.example.com'))
            ->assertForbidden();

        // Correct secret + entitled user → allowed.
        $this->withHeaders(['X-Forward-Auth-Secret' => 's3cret'])
            ->get(self::VERIFY, $this->forwarded('app.example.com'))
            ->assertOk();
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

    public function test_admin_can_view_the_forward_auth_page(): void
    {
        config(['auth.admin_emails' => ['admin@example.com']]);
        $admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);
        ProxyApp::factory()->pending()->create(['host' => 'pending.example.com']);

        $this->actingAs($admin)
            ->get(route('admin.forward-auth'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ForwardAuth/Index')
                ->has('apps', 1)
                ->has('teams'));
    }

    public function test_non_admin_cannot_view_the_forward_auth_page(): void
    {
        config(['auth.admin_emails' => ['admin@example.com']]);
        $user = User::factory()->withPersonalTeam()->create(['email' => 'someone@example.com']);

        $this->actingAs($user)->get(route('admin.forward-auth'))->assertNotFound();
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
