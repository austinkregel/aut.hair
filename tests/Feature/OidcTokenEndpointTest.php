<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Tests\TestCase;

class OidcTokenEndpointTest extends TestCase
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

    public function test_auth_code_pkce_returns_rs256_id_token()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);

        $client = app(ClientRepository::class)->create($user->id, 'Test Auth Code', 'http://localhost/callback');
        $client->team_id = $team->id;
        $client->save();

        $codeVerifier = str_repeat('a', 64); // PKCE requires 43-128 chars
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $state = 'xyz';

        $this->actingAs($user);

        $authResponse = $this->get('/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'team_id' => $team->id,
        ]));

        $authResponse->assertStatus(200);

        $approve = $this->post('/oauth/authorize', [
            'state' => $state,
            'client_id' => $client->id,
            'response_type' => 'code',
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'approve' => 'Approve',
            'team_id' => $team->id,
        ]);

        $approve->assertRedirect();
        $location = $approve->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame($state, $query['state']);
        $this->assertNotEmpty($query['code']);

        // Ensure auth code carries team_id
        \Illuminate\Support\Facades\DB::table('oauth_auth_codes')
            ->where('id', $query['code'])
            ->update(['team_id' => $team->id]);

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'redirect_uri' => $client->redirect,
            'code' => $query['code'],
            'code_verifier' => $codeVerifier,
        ]);

        $tokenResponse->assertStatus(200);
        $tokenResponse->assertJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'refresh_token',
            'id_token',
        ]);

        $idToken = $tokenResponse->json('id_token');
        $this->assertNotEmpty($idToken);

        $parser = new Parser(new JoseEncoder());
        $jwt = $parser->parse($idToken);
        $validator = new Validator();
        $validator->assert(
            $jwt,
            new LooseValidAt(new SystemClock(new \DateTimeZone('UTC'))),
            new SignedWith(
                new Sha256(),
                InMemory::file(base_path('tests/Feature/test-public.key'))
            )
        );

        $claims = $jwt->claims()->all();
        $this->assertArrayHasKey('sub', $claims);
        $this->assertArrayHasKey('aud', $claims);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertEquals('RS256', $jwt->headers()->get('alg'));
    }

    public function test_password_grant_does_not_issue_id_token()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $client = app(ClientRepository::class)->createPasswordGrantClient(null, 'Password Client', 'http://localhost');

        $response = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'username' => $user->email,
            'password' => 'secret',
            'scope' => 'openid profile email',
        ]);

        $response->assertStatus(200);
        $this->assertNull($response->json('id_token'), 'id_token must not be issued for password grant');
    }

    public function test_auth_code_pkce_with_nonce_sets_nonce_and_auth_time()
    {
        $fixedNow = Carbon::create(2024, 1, 1, 0, 0, 0, 'UTC');
        Carbon::setTestNow($fixedNow);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);

        $client = app(ClientRepository::class)->create($user->id, 'Test Auth Code Nonce', 'http://localhost/callback');
        $client->team_id = $team->id;
        $client->save();

        $codeVerifier = str_repeat('c', 64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $nonce = 'nonce-xyz-123';
        $state = 'state-123';

        $this->actingAs($user);

        $this->get('/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'nonce' => $nonce,
            'team_id' => $team->id,
        ]))->assertStatus(200);

        $approve = $this->post('/oauth/authorize', [
            'state' => $state,
            'client_id' => $client->id,
            'response_type' => 'code',
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'nonce' => $nonce,
            'approve' => 'Approve',
            'team_id' => $team->id,
        ]);

        $approve->assertRedirect();
        parse_str(parse_url($approve->headers->get('Location'), PHP_URL_QUERY), $query);

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'redirect_uri' => $client->redirect,
            'code' => $query['code'],
            'code_verifier' => $codeVerifier,
        ]);

        $tokenResponse->assertStatus(200);
        $idToken = $tokenResponse->json('id_token');
        $this->assertNotEmpty($idToken);

        $jwt = (new Parser(new JoseEncoder()))->parse($idToken);
        $claims = $jwt->claims()->all();

        $this->assertNotNull($claims['nonce'] ?? null);
        $this->assertEquals($nonce, $claims['nonce'] ?? null);
        $this->assertArrayHasKey('auth_time', $claims);
        $this->assertTrue(abs($claims['auth_time'] - $fixedNow->timestamp) < 10, 'auth_time should be near now');

        Carbon::setTestNow(null);
    }

    public function test_plain_pkce_is_rejected()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);

        $client = app(ClientRepository::class)->create($user->id, 'Test Auth Code Plain', 'http://localhost/callback');
        $client->team_id = $team->id;
        $client->save();

        $this->actingAs($user);

        $response = $this->get('/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => 'plain-state',
            'code_challenge' => 'plain-challenge',
            'code_challenge_method' => 'plain',
            'team_id' => $team->id,
        ]));

        $response->assertStatus(400);
    }

    /**
     * Test that the token endpoint accepts client_secret_basic authentication
     * where client_id and client_secret are provided in the Authorization header
     * rather than in the request body.
     */
    public function test_auth_code_with_client_secret_basic_auth_succeeds()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);

        $client = app(ClientRepository::class)->create($user->id, 'Test Basic Auth', 'http://localhost/callback');
        $client->team_id = $team->id;
        $client->save();

        $codeVerifier = str_repeat('b', 64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $state = 'basic-auth-state';

        $this->actingAs($user);

        $authResponse = $this->get('/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'team_id' => $team->id,
        ]));

        $authResponse->assertStatus(200);

        $approve = $this->post('/oauth/authorize', [
            'state' => $state,
            'client_id' => $client->id,
            'response_type' => 'code',
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'approve' => 'Approve',
            'team_id' => $team->id,
        ]);

        $approve->assertRedirect();
        $location = $approve->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame($state, $query['state']);
        $this->assertNotEmpty($query['code']);

        // Ensure auth code carries team_id
        \Illuminate\Support\Facades\DB::table('oauth_auth_codes')
            ->where('id', $query['code'])
            ->update(['team_id' => $team->id]);

        // Use HTTP Basic auth (client_secret_basic) instead of client_id/client_secret in body.
        // This is a standard OAuth2 authentication method per RFC 6749 Section 2.3.1.
        $basicAuth = base64_encode($client->id . ':' . $client->secret);

        $tokenResponse = $this->withHeaders([
            'Authorization' => 'Basic ' . $basicAuth,
        ])->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'redirect_uri' => $client->redirect,
            'code' => $query['code'],
            'code_verifier' => $codeVerifier,
            // Notably: client_id is NOT in the body - it's in the Authorization header
        ]);

        $tokenResponse->assertStatus(200);
        $tokenResponse->assertJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'refresh_token',
            'id_token',
        ]);

        $idToken = $tokenResponse->json('id_token');
        $this->assertNotEmpty($idToken);
    }
}
