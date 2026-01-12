<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Tests\TestCase;

class MachineTokenEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_lists_only_confidential_client_credentials_clients_for_user(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        Client::query()->create([
            'user_id' => $user->id,
            'name' => 'Machine Client',
            'secret' => 'secret',
            'redirect' => '',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
            'grant_types' => ['client_credentials'],
        ]);

        Client::query()->create([
            'user_id' => $user->id,
            'name' => 'PKCE Client',
            'secret' => null,
            'redirect' => 'http://localhost/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
            'grant_types' => ['authorization_code'],
        ]);

        $response = $this->getJson('/oauth/machine-tokens/clients');
        $response->assertOk();
        $response->assertJsonCount(1, 'clients');
        $response->assertJsonFragment(['name' => 'Machine Client']);
        $response->assertJsonMissing(['secret' => 'secret']);
    }

    public function test_can_generate_list_and_revoke_machine_tokens_for_owned_client(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $client = Client::query()->create([
            'user_id' => $user->id,
            'name' => 'Machine Client',
            'secret' => 'secret',
            'redirect' => '',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
            'grant_types' => ['client_credentials'],
        ]);

        $generate = $this->postJson('/oauth/machine-tokens/generate', [
            'client_id' => (string) $client->id,
            'scopes' => ['openid'],
        ]);

        $generate->assertOk();
        $generate->assertJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'token_id',
        ]);

        $tokenId = $generate->json('token_id');
        $this->assertNotEmpty($tokenId);

        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenId,
            'client_id' => $client->id,
            'user_id' => null,
            'revoked' => 0,
        ]);

        $list = $this->getJson("/oauth/machine-tokens/{$client->id}/tokens");
        $list->assertOk();
        $list->assertJsonFragment(['id' => $tokenId]);

        $revoke = $this->deleteJson("/oauth/machine-tokens/tokens/{$tokenId}");
        $revoke->assertOk();
        $revoke->assertJson(['revoked' => true]);

        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenId,
            'revoked' => 1,
        ]);
    }
}
