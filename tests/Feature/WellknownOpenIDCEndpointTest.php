<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class WellknownOpenIDCEndpointTest extends TestCase
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

    /**
     * Test the /api/userinfo endpoint returns OIDC-compliant claims.
     *
     * @return void
     */
    public function test_we_can_fetch_openid_configuration()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        Passport::actingAs($user, ['openid', 'profile', 'email']);

        $response = $this->getJson(route('well-known'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'issuer',
                'authorization_endpoint',
                'token_endpoint',
                'jwks_uri',
                'userinfo_endpoint',
                'machine_info_endpoint',
                'response_types_supported',
                'response_modes_supported',
                'grant_types_supported',
                'scopes_supported',
                'claims_supported',
                'token_endpoint_auth_methods_supported',
                'code_challenge_methods_supported',
                'id_token_signing_alg_values_supported',
            ]);

        $body = $response->json();
        $this->assertEquals(['code'], $body['response_types_supported']);
        $this->assertEquals(['authorization_code', 'refresh_token', 'client_credentials'], $body['grant_types_supported']);
        $this->assertEquals(['RS256'], $body['id_token_signing_alg_values_supported']);
    }
}
