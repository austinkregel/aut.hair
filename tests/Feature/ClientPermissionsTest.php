<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Token;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Tests\TestCase;

class ClientPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);
    }

    protected function makeClient(int $teamId): Client
    {
        return Client::forceCreate([
            'name' => 'backup-server',
            'secret' => 'secret',
            'redirect' => 'https://example.test/auth/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
            'team_id' => $teamId,
        ]);
    }

    /** Sign the user in as the bearer of a token issued to $client. */
    protected function actAsBearer(User $user, Client $client, array $scopes = ['openid']): void
    {
        $token = Token::forceCreate([
            'id' => 'jti-'.uniqid(),
            'user_id' => $user->id,
            'client_id' => $client->id,
            'name' => 'test',
            'scopes' => $scopes,
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user->withAccessToken($token), 'api');
    }

    public function test_returns_teams_and_permissions_for_the_calling_client(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $client = $this->makeClient($team->id);

        // Team owns the client, so getEffectivePermissionsForClient grants the
        // admin role's permissions.
        DB::table('oauth_clients')->where('id', $client->id)->update(['team_id' => $team->id]);

        $this->actAsBearer($user, $client);

        $response = $this->getJson('/api/client-permissions');

        $response->assertOk()
            ->assertJsonPath('sub', (string) $user->id)
            ->assertJsonPath('client_id', (string) $client->id);

        $this->assertContains((string) $team->id, $response->json('teams'));
        $this->assertContains('delete', $response->json('permissions'),
            'the owning team should reach the admin role, which is the only role granting delete');
    }

    public function test_403_not_entitled_when_no_team_grants_access(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $other = Team::forceCreate([
            'user_id' => User::factory()->create()->id,
            'name' => 'unrelated',
            'personal_team' => false,
        ]);
        $client = $this->makeClient($other->id);

        $this->actAsBearer($user, $client);

        // Distinguishable from a transport failure: the relying party must be
        // able to tell "revoked" from "aut.hair is down" and fail closed
        // differently for each.
        $this->getJson('/api/client-permissions')
            ->assertStatus(403)
            ->assertJsonPath('error', 'not_entitled');
    }

    public function test_403_insufficient_scope_without_openid(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $client = $this->makeClient($team->id);

        $this->actAsBearer($user, $client, ['profile']);

        $this->getJson('/api/client-permissions')
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_scope');
    }

    public function test_401_without_authentication(): void
    {
        $this->getJson('/api/client-permissions')->assertStatus(401);
    }
}
