<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Tests\TestCase;

class OidcTokenRevocationTest extends TestCase
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

    public function test_access_token_can_be_revoked_with_client_auth()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $client = app(\Laravel\Passport\ClientRepository::class)
            ->createPasswordGrantClient(null, 'Password Client', 'http://localhost');

        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'username' => $user->email,
            'password' => 'secret',
            'scope' => 'openid',
        ]);

        $accessToken = $tokenResponse->json('access_token');
        $this->assertNotEmpty($accessToken);

        $revocation = $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode($client->id.':'.$client->secret),
        ])->postJson('/oauth/revoke', [
            'token' => $accessToken,
            'token_type_hint' => 'access_token',
        ]);

        $revocation->assertStatus(200)->assertJson(['revoked' => true]);

        $parser = new Parser(new JoseEncoder);
        $jti = $parser->parse($accessToken)->claims()->get('jti');
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $jti,
            'revoked' => 1,
        ]);
    }

    public function test_rejection_when_client_is_invalid()
    {
        $response = $this->postJson('/oauth/revoke', [
            'token' => 'whatever',
        ]);

        $response->assertStatus(401);
    }
}
