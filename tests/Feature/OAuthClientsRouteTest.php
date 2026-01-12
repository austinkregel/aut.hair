<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Tests\TestCase;

class OAuthClientsRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_clients_index_requires_authentication(): void
    {
        $response = $this->get('/oauth/clients');

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_list_clients_for_current_team_and_switch_by_team_id(): void
    {
        $owner = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $teamB = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);

        $owner->teams()->attach($teamA, ['role' => 'admin']);
        $owner->teams()->attach($teamB, ['role' => 'admin']);
        $owner->switchTeam($teamA);

        $clientA = Client::create([
            'user_id' => null,
            'team_id' => $teamA->id,
            'name' => 'Team A Client',
            'secret' => 'secret',
            'redirect' => 'http://localhost/a/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);

        $clientB = Client::create([
            'user_id' => null,
            'team_id' => $teamB->id,
            'name' => 'Team B Client',
            'secret' => 'secret',
            'redirect' => 'http://localhost/b/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);

        $this->actingAs($owner);

        $resp = $this->getJson('/oauth/clients');
        $resp->assertOk();
        $resp->assertJsonFragment(['id' => $clientA->id, 'name' => $clientA->name]);
        $resp->assertJsonMissing(['id' => $clientB->id, 'name' => $clientB->name]);

        $resp = $this->getJson('/oauth/clients?team_id='.$teamB->id);
        $resp->assertOk();
        $resp->assertJsonFragment(['id' => $clientB->id, 'name' => $clientB->name]);
        $resp->assertJsonMissing(['id' => $clientA->id, 'name' => $clientA->name]);
    }

    public function test_owner_can_create_update_and_delete_client_for_selected_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->teams()->attach($team, ['role' => 'admin']);
        $owner->switchTeam($team);

        $this->actingAs($owner);

        $create = $this->postJson('/oauth/clients', [
            'team_id' => $team->id,
            'name' => 'Created Client',
            'redirect' => 'http://localhost/callback',
            'confidential' => false,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'scopes' => ['openid', 'profile'],
        ]);

        $create->assertOk();
        $createdId = $create->json('id');
        $this->assertNotNull($createdId);

        $this->assertDatabaseHas('oauth_clients', [
            'id' => $createdId,
            'team_id' => $team->id,
            'revoked' => 0,
            'name' => 'Created Client',
        ]);

        $update = $this->putJson('/oauth/clients/'.$createdId, [
            'team_id' => $team->id,
            'name' => 'Updated Client',
            'redirect' => 'http://localhost/updated',
            'confidential' => true,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'scopes' => ['openid'],
        ]);

        $update->assertOk();
        $this->assertDatabaseHas('oauth_clients', [
            'id' => $createdId,
            'team_id' => $team->id,
            'name' => 'Updated Client',
            'redirect' => 'http://localhost/updated',
            'revoked' => 0,
        ]);

        $delete = $this->delete('/oauth/clients/'.$createdId);
        $delete->assertNoContent();

        $this->assertDatabaseHas('oauth_clients', [
            'id' => $createdId,
            'revoked' => 1,
        ]);
    }

    public function test_non_owner_cannot_create_update_or_delete_clients(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);

        $owner->teams()->attach($team, ['role' => 'admin']);
        $member->teams()->attach($team, ['role' => 'admin']);
        $member->switchTeam($team);

        $client = Client::create([
            'user_id' => null,
            'team_id' => $team->id,
            'name' => 'Team Client',
            'secret' => 'secret',
            'redirect' => 'http://localhost/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);

        $this->actingAs($member);

        $this->postJson('/oauth/clients', [
            'team_id' => $team->id,
            'name' => 'Nope',
            'redirect' => 'http://localhost/callback',
        ])->assertForbidden();

        $this->putJson('/oauth/clients/'.$client->id, [
            'team_id' => $team->id,
            'name' => 'Nope',
            'redirect' => 'http://localhost/updated',
        ])->assertForbidden();

        $this->delete('/oauth/clients/'.$client->id)->assertForbidden();
    }

    public function test_user_cannot_update_or_delete_a_client_from_another_team_by_passing_a_team_they_own(): void
    {
        $teamAOwner = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $teamAOwner->id, 'personal_team' => false]);
        $teamAOwner->teams()->attach($teamA, ['role' => 'admin']);

        $attacker = User::factory()->create();
        $teamB = Team::factory()->create(['user_id' => $attacker->id, 'personal_team' => false]);
        $attacker->teams()->attach($teamB, ['role' => 'admin']);
        $attacker->switchTeam($teamB);

        $victimClient = Client::create([
            'user_id' => null,
            'team_id' => $teamA->id,
            'name' => 'Victim Client',
            'secret' => 'secret',
            'redirect' => 'http://localhost/victim',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);

        $this->actingAs($attacker);

        $this->putJson('/oauth/clients/'.$victimClient->id, [
            'team_id' => $teamB->id,
            'name' => 'Stolen',
            'redirect' => 'http://localhost/stolen',
        ])->assertForbidden();

        $this->delete('/oauth/clients/'.$victimClient->id)->assertForbidden();
    }
}
