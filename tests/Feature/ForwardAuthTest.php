<?php

namespace Tests\Feature;

use App\Models\ProxyApp;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForwardAuthTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY = '/outpost.goauthentik.io/auth/nginx';

    private function forwarded(string $host, string $uri = '/', string $proto = 'https'): array
    {
        return [
            'X-Forwarded-Host' => $host,
            'X-Forwarded-Proto' => $proto,
            'X-Forwarded-Uri' => $uri,
            'X-Forwarded-Method' => 'GET',
        ];
    }

    public function test_unregistered_host_is_forbidden(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(self::VERIFY, $this->forwarded('unknown.example.com'))
            ->assertForbidden();
    }

    public function test_disabled_app_is_forbidden(): void
    {
        $app = ProxyApp::factory()->disabled()->create(['host' => 'app.example.com']);
        $user = $app->ownerTeam->owner;

        $this->actingAs($user)
            ->get(self::VERIFY, $this->forwarded('app.example.com'))
            ->assertForbidden();
    }

    public function test_anonymous_request_redirects_to_login_and_stashes_original_url(): void
    {
        ProxyApp::factory()->create(['host' => 'app.example.com']);

        $response = $this->get(self::VERIFY, $this->forwarded('app.example.com', '/dashboard?x=1'));

        $response->assertStatus(302);
        // Login must live on aut.hair's own host, NOT the forwarded app host
        // (which trustForwardHeader would otherwise poison the URL generator with).
        $location = $response->headers->get('Location');
        $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url($location, PHP_URL_HOST));
        $this->assertStringEndsWith('/login', (string) parse_url($location, PHP_URL_PATH));
        $this->assertNotSame('app.example.com', parse_url($location, PHP_URL_HOST));

        $this->assertSame('https://app.example.com/dashboard?x=1', session('url.intended'));
    }

    public function test_return_url_stays_on_the_app_host_even_with_a_scheme_relative_uri(): void
    {
        ProxyApp::factory()->create(['host' => 'app.example.com']);

        $this->get(self::VERIFY, $this->forwarded('app.example.com', '//evil.com/steal'));

        $intended = session('url.intended');
        $this->assertSame('app.example.com', parse_url($intended, PHP_URL_HOST));
    }

    public function test_authenticated_but_unentitled_user_is_forbidden(): void
    {
        ProxyApp::factory()->create(['host' => 'app.example.com']);

        $outsider = User::factory()->withPersonalTeam()->create();

        $this->actingAs($outsider)
            ->get(self::VERIFY, $this->forwarded('app.example.com'))
            ->assertForbidden();
    }

    public function test_owner_team_member_is_allowed_with_identity_headers(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $owner = $team->owner;
        ProxyApp::factory()->create(['host' => 'app.example.com', 'team_id' => $team->id]);

        $response = $this->actingAs($owner)
            ->get(self::VERIFY, $this->forwarded('app.example.com'));

        $response->assertOk();
        $response->assertHeader('X-authentik-username', $owner->email);
        $response->assertHeader('X-authentik-email', $owner->email);
        $response->assertHeader('X-authentik-groups', (string) $team->id);
    }

    public function test_granted_team_member_is_allowed(): void
    {
        $ownerTeam = Team::factory()->create(['personal_team' => false]);
        $grantedTeam = Team::factory()->create(['personal_team' => false]);
        $member = $grantedTeam->owner;

        $app = ProxyApp::factory()->create([
            'host' => 'app.example.com',
            'team_id' => $ownerTeam->id,
        ]);
        $app->teams()->attach($grantedTeam);

        $this->actingAs($member)
            ->get(self::VERIFY, $this->forwarded('app.example.com'))
            ->assertOk();
    }

    public function test_groups_header_dedupes_a_team_that_is_both_owned_and_joined(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $owner = $team->owner;
        // Also join the same team as a member -> allTeams() would list it twice.
        $owner->teams()->attach($team, ['role' => 'admin']);

        ProxyApp::factory()->create(['host' => 'app.example.com', 'team_id' => $team->id]);

        $response = $this->actingAs($owner)
            ->get(self::VERIFY, $this->forwarded('app.example.com'));

        $response->assertOk();
        $response->assertHeader('X-authentik-groups', (string) $team->id);
    }

    public function test_groups_header_only_exposes_teams_relevant_to_this_app(): void
    {
        // User belongs to two teams; the app only allows one of them.
        $allowedTeam = Team::factory()->create(['personal_team' => false]);
        $privateTeam = Team::factory()->create(['personal_team' => false]);
        $user = $allowedTeam->owner;
        $user->teams()->attach($privateTeam, ['role' => 'admin']);

        ProxyApp::factory()->create(['host' => 'app.example.com', 'team_id' => $allowedTeam->id]);

        $response = $this->actingAs($user)
            ->get(self::VERIFY, $this->forwarded('app.example.com'));

        $response->assertOk();
        // Only the entitling team leaks — not the unrelated private team.
        $response->assertHeader('X-authentik-groups', (string) $allowedTeam->id);
    }

    public function test_allows_user_truth_table(): void
    {
        $ownerTeam = Team::factory()->create(['personal_team' => false]);
        $grantedTeam = Team::factory()->create(['personal_team' => false]);
        $otherTeam = Team::factory()->create(['personal_team' => false]);

        $app = ProxyApp::factory()->create(['team_id' => $ownerTeam->id]);
        $app->teams()->attach($grantedTeam);

        $this->assertTrue($app->allowsUser($ownerTeam->owner), 'owner team member allowed');
        $this->assertTrue($app->allowsUser($grantedTeam->owner), 'granted team member allowed');
        $this->assertFalse($app->allowsUser($otherTeam->owner), 'unrelated team member denied');
    }
}
