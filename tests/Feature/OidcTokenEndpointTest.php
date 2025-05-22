<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class OidcTokenEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // // Point Passport to the test keys
        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);
    }

    public function test_oauth_token_endpoint_issues_access_token_and_id_token()
    {
        // Create a user and a password grant client
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);
        $client = app(ClientRepository::class)->createPasswordGrantClient(null, 'Test Password Grant', 'http://localhost');

        // Request a token with openid scope
        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'username' => $user->email,
            'password' => 'secret',
            'scope' => 'openid',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'id_token', // Uncomment this when id_token is implemented
        ]);
        $this->assertNotEmpty($response->json('id_token'));
    }

    public function test_id_token_is_jwt_and_contains_required_oidc_claims()
    {
        $user = \App\Models\User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);
        $client = app(\Laravel\Passport\ClientRepository::class)->createPasswordGrantClient(null, 'Test Password Grant', 'http://localhost');

        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'username' => $user->email,
            'password' => 'secret',
            'scope' => 'openid profile email',
        ]);

        $response->assertStatus(200);
        $idToken = $response->json('id_token');
        $this->assertNotEmpty($idToken);

        // Decode JWT (header.payload.signature)
        $parts = explode('.', $idToken);
        $this->assertCount(3, $parts, 'id_token must be a JWT with 3 parts');
        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode(base64_decode(strtr($headerB64, '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
        $this->assertIsArray($header);
        $this->assertIsArray($payload);

        // Check required OIDC claims
        $this->assertArrayHasKey('iss', $payload);
        $this->assertArrayHasKey('sub', $payload);
        $this->assertArrayHasKey('aud', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('iat', $payload);
        // After a long thought, and a long consultation with the RFC turns out HS256 is the only required algorithm
        // @see https://datatracker.ietf.org/doc/html/rfc7519#section-8
        $this->assertSame('HS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);

        // Never ever trim the contents of the hmac secret. Never. Don't do it. It's a bad idea.
        $hmacSecret = file_get_contents(base_path('tests/Feature/test-private.key'));
        $signedData = $headerB64.'.'.$payloadB64;
        $expectedSignature = hash_hmac('sha256', $signedData, $hmacSecret, true);
        $expectedSignatureB64 = rtrim(strtr(base64_encode($expectedSignature), '+/', '-_'), '=');

        // Compare the base64url-encoded signature directly
        $this->assertSame($expectedSignatureB64, $signatureB64, 'id_token signature must be valid');
    }
}
