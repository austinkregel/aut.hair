<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class OidcFullWorkflowTest extends TestCase
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

    public function test_full_oidc_workflow_fetches_valid_token_and_userinfo()
    {
        // Step 1: Fetch the well-known OpenID configuration
        $wellKnownResponse = $this->getJson('/.well-known/openid-configuration');
        $wellKnownResponse->assertStatus(200);
        $config = $wellKnownResponse->json();
        $this->assertArrayHasKey('token_endpoint', $config);
        $this->assertArrayHasKey('userinfo_endpoint', $config);
        $tokenEndpoint = $config['token_endpoint'];
        $userinfoEndpoint = $config['userinfo_endpoint'];

        // Step 2: Create a user and a password grant client
        $user = User::factory()->create([
            'email_verified_at' => now()->subDay(),
            'password' => bcrypt('secret'),
        ]);
        $client = app(ClientRepository::class)->createPasswordGrantClient(null, 'Test Password Grant', 'http://localhost');

        // Step 3: Request a token with openid scope using the discovered token endpoint
        $response = $this->postJson($tokenEndpoint, [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'username' => $user->email,
            'password' => 'secret',
            'scope' => 'openid profile email',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'id_token',
        ]);
        $accessToken = $response->json('access_token');
        $this->assertNotEmpty($accessToken);
        $this->assertNotEmpty($response->json('id_token'));

        // Step 4: Use the access token to fetch userinfo from the discovered userinfo endpoint
        $userinfo = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->getJson($userinfoEndpoint);

        $userinfo->assertStatus(200);
        $userinfo->assertJsonStructure([
            'sub', 'name', 'picture', 'email', 'email_verified',
        ]);
        $this->assertEquals($user->email, $userinfo->json('email'));
    }
}
