<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
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
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
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

        // Step 2: Create a user and an authorization code client
        $user = User::factory()->create([
            'email_verified_at' => now()->subDay(),
            'password' => bcrypt('secret'),
        ]);
        $client = app(ClientRepository::class)->create($user->id, 'Test Auth Code', 'http://localhost/callback');

        $codeVerifier = str_repeat('b', 64); // PKCE requires 43-128 chars
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $state = 'abc123';

        $this->actingAs($user);

        $this->get('/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]))->assertStatus(200);

        $approve = $this->post('/oauth/authorize', [
            'state' => $state,
            'client_id' => $client->id,
            'response_type' => 'code',
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'approve' => 'Approve',
        ]);

        $approve->assertRedirect();
        parse_str(parse_url($approve->headers->get('Location'), PHP_URL_QUERY), $query);
        $authCode = $query['code'];

        // Step 3: Exchange code for tokens with openid scope using the discovered token endpoint
        $response = $this->post($tokenEndpoint, [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'redirect_uri' => $client->redirect,
            'code' => $authCode,
            'code_verifier' => $codeVerifier,
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

        // Ensure ID token corresponds to user sub
        $parser = new Parser(new JoseEncoder());
        $idToken = $parser->parse($response->json('id_token'));
        $this->assertEquals((string) $user->id, $idToken->claims()->get('sub'));

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
