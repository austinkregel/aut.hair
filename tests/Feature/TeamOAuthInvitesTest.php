<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Tests\TestCase;

class TeamOAuthInvitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_and_remove_team(): void
    {
        $owner = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $teamB = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->teams()->attach($teamA, ['role' => 'admin']);
        $owner->teams()->attach($teamB, ['role' => 'admin']);

        $client = Client::create([
            'user_id' => null,
            'team_id' => $teamA->id,
            'name' => 'Invite Client',
            'secret' => 'secret',
            'redirect' => 'http://localhost/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);

        $this->actingAs($owner);

        $resp = $this->post("/teams/{$teamA->id}/oauth-clients/{$client->id}/invite-team", [
            'invited_team_id' => $teamB->id,
            'role' => 'login',
        ]);
        $resp->assertStatus(204);

        $this->assertDatabaseHas('oauth_client_team_invitations', [
            'inviting_team_id' => $teamA->id,
            'invited_team_id' => $teamB->id,
            'oauth_client_id' => $client->id,
        ]);

        $resp = $this->delete("/teams/{$teamA->id}/oauth-clients/{$client->id}/teams/{$teamB->id}");
        $resp->assertStatus(204);

        $this->assertDatabaseMissing('oauth_client_team_invitations', [
            'inviting_team_id' => $teamA->id,
            'invited_team_id' => $teamB->id,
            'oauth_client_id' => $client->id,
        ]);
    }

    public function test_non_owner_cannot_invite(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $teamB = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->teams()->attach($teamA, ['role' => 'admin']);
        $owner->teams()->attach($teamB, ['role' => 'admin']);

        $client = Client::create([
            'user_id' => null,
            'team_id' => $teamA->id,
            'name' => 'Invite Client',
            'secret' => 'secret',
            'redirect' => 'http://localhost/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);

        $this->actingAs($intruder);

        $resp = $this->post("/teams/{$teamA->id}/oauth-clients/{$client->id}/invite-team", [
            'invited_team_id' => $teamB->id,
            'role' => 'login',
        ]);
        $resp->assertStatus(403);

        $this->assertDatabaseMissing('oauth_client_team_invitations', [
            'inviting_team_id' => $teamA->id,
            'invited_team_id' => $teamB->id,
            'oauth_client_id' => $client->id,
        ]);
    }

    public function test_inviting_a_team_to_a_second_client_does_not_overwrite_the_first(): void
    {
        $owner = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $teamB = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->teams()->attach($teamA, ['role' => 'admin']);
        $owner->teams()->attach($teamB, ['role' => 'admin']);

        $makeClient = fn (string $name) => Client::create([
            'user_id' => null,
            'team_id' => $teamA->id,
            'name' => $name,
            'secret' => 'secret',
            'redirect' => 'http://localhost/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);

        $clientOne = $makeClient('Client One');
        $clientTwo = $makeClient('Client Two');

        $this->actingAs($owner);

        $this->post("/teams/{$teamA->id}/oauth-clients/{$clientOne->id}/invite-team", [
            'invited_team_id' => $teamB->id,
            'role' => 'login',
        ])->assertStatus(204);

        // Inviting the same team to a second client must not overwrite the first grant.
        $this->post("/teams/{$teamA->id}/oauth-clients/{$clientTwo->id}/invite-team", [
            'invited_team_id' => $teamB->id,
            'role' => 'login',
        ])->assertStatus(204);

        $this->assertDatabaseHas('oauth_client_team_invitations', [
            'inviting_team_id' => $teamA->id,
            'invited_team_id' => $teamB->id,
            'oauth_client_id' => $clientOne->id,
        ]);
        $this->assertDatabaseHas('oauth_client_team_invitations', [
            'inviting_team_id' => $teamA->id,
            'invited_team_id' => $teamB->id,
            'oauth_client_id' => $clientTwo->id,
        ]);

        $this->assertSame(2, DB::table('oauth_client_team_invitations')
            ->where('inviting_team_id', $teamA->id)
            ->where('invited_team_id', $teamB->id)
            ->count());
    }
}
