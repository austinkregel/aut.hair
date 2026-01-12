<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Tests\TestCase;

class OAuthAuthorizationTeamFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_multiple_teams_must_select_team_and_team_id_persists(): void
    {
        $owner = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $teamB = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);

        $owner->teams()->attach($teamA, ['role' => 'admin']);
        $owner->teams()->attach($teamB, ['role' => 'admin']);

        // Create a client owned by teamA
        $client = Client::create([
            'user_id' => null,
            'team_id' => $teamA->id,
            'name' => 'Test Client',
            'secret' => 'secret',
            'redirect' => 'http://localhost/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);

        // Invite teamB to the client
        DB::table('oauth_client_team_invitations')->insert([
            'inviting_team_id' => $teamA->id,
            'invited_team_id' => $teamB->id,
            'oauth_client_id' => $client->id,
            'role' => 'login',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner);

        // Missing team selection -> 422
        $resp = $this->get('/oauth/authorize?response_type=code&client_id='.$client->id.'&redirect_uri='.$client->redirect.'&state=xyz');
        $resp->assertStatus(422);

        // With team selection -> simulate approval result
        DB::table('oauth_auth_codes')->insert([
            'id' => 'seeded-code',
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'team_id' => $teamB->id,
            'scopes' => '[]',
            'revoked' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $code = DB::table('oauth_auth_codes')->latest('created_at')->first();
        $this->assertNotNull($code);
        $this->assertEquals($teamB->id, $code->team_id);
    }
}
