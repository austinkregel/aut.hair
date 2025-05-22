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
                'sub', 'name', 'picture', 'email', 'email_verified',
            ]);

    }
}
