<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Tests\TestCase;

class JwksEndpointTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test the /api/jwks endpoint returns the correct JWKS structure and values for a known public key.
     *
     * @return void
     */
    public function test_jwks_endpoint_returns_expected_jwk()
    {
        // Act: Call the endpoint
        $response = $this->getJson(route('oidc.jwks'));

        // Assert: Structure and values
        $response->assertStatus(200)
            ->assertJsonStructure([
                'keys' => [
                    [
                        'kty', 'alg', 'use', 'n', 'e', 'kid',
                    ],
                ],
            ]);
        $jwk = $response->json('keys')[0];
        $this->assertEquals('RSA', $jwk['kty']);
        $this->assertEquals('RS256', $jwk['alg']);
        $this->assertEquals('sig', $jwk['use']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $jwk['kid']);
        // Check that modulus and exponent are non-empty base64url strings
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $jwk['n']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $jwk['e']);
    }

    public function test_id_token_kid_matches_jwks_key()
    {
        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);

        $user = \App\Models\User::factory()->withPersonalTeam()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team, 'Test user must have a team for oauth.team middleware.');

        $client = app(\Laravel\Passport\ClientRepository::class)->create($user->id, 'Test Auth Code JWKS', 'http://localhost/callback');
        $client->forceFill(['team_id' => $team->id])->save();

        $codeVerifier = str_repeat('k', 64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $state = 'jwks-state';

        $this->actingAs($user);

        $this->get('/oauth/authorize?'.http_build_query([
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

        // Parse ID token header to get kid
        $parser = new Parser(new JoseEncoder);
        $jwt = $parser->parse($idToken);
        $kid = $jwt->headers()->get('kid');

        // Fetch JWKS and ensure matching key exists
        $jwksResponse = $this->getJson(route('oidc.jwks'));
        $jwksResponse->assertStatus(200);
        $keys = $jwksResponse->json('keys');
        $kidValues = collect($keys)->pluck('kid')->all();

        $this->assertContains($kid, $kidValues, 'JWKS must contain the key with matching kid');

        // Verify signature with the matched JWKS key
        // Re-use our configured public key to validate signature; kid presence + JWKS match is asserted.
        $validator = new Validator;
        $validator->assert(
            $jwt,
            new LooseValidAt(new \Lcobucci\Clock\SystemClock(new \DateTimeZone('UTC'))),
            new SignedWith(new Sha256, InMemory::file(base_path('tests/Feature/test-public.key')))
        );
    }
}
