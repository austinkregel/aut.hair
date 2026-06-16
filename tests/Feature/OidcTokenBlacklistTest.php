<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Tests\TestCase;

/**
 * The OidcTokenBlacklistMiddleware makes token revocation actually take effect
 * on bearer-protected resource routes — these OIDC access tokens are
 * self-contained JWTs that `auth:api` alone does not reject once revoked.
 */
class OidcTokenBlacklistTest extends TestCase
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

    private function issueToken(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $client = app(\Laravel\Passport\ClientRepository::class)
            ->createPasswordGrantClient(null, 'Password Client', 'http://localhost');

        $accessToken = $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'username' => $user->email,
            'password' => 'secret',
            'scope' => 'openid profile email',
        ])->json('access_token');

        $this->assertNotEmpty($accessToken);

        return [$accessToken, $client];
    }

    public function test_revoking_via_oauth_revoke_blocks_userinfo(): void
    {
        [$accessToken, $client] = $this->issueToken();

        // Valid before revocation.
        $this->withToken($accessToken)->getJson(route('oidc.userinfo'))->assertStatus(200);

        // Standard RFC 7009 revoke (flips oauth_access_tokens.revoked).
        $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode($client->id.':'.$client->secret),
        ])->postJson('/oauth/revoke', [
            'token' => $accessToken,
            'token_type_hint' => 'access_token',
        ])->assertStatus(200);

        // Now rejected — the middleware honors the revoked flag.
        $this->withToken($accessToken)->getJson(route('oidc.userinfo'))->assertStatus(401);
    }

    public function test_cache_blacklisted_token_is_rejected(): void
    {
        [$accessToken] = $this->issueToken();

        $this->withToken($accessToken)->getJson(route('oidc.userinfo'))->assertStatus(200);

        $jti = (new Parser(new JoseEncoder()))->parse($accessToken)->claims()->get('jti');
        Cache::put('oidc_token_blacklist:'.$jti, true, now()->addDay());

        $this->withToken($accessToken)->getJson(route('oidc.userinfo'))->assertStatus(401);
    }
}
