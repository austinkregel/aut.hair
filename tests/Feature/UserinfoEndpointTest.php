<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UserinfoEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
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
    public function test_userinfo_endpoint_returns_oidc_claims()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        Passport::actingAs($user, ['openid', 'profile', 'email']);

        $response = $this->getJson('/api/userinfo');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'sub', 'name', 'picture', 'email', 'email_verified',
            ]);
        $this->assertSame((string) $user->id, $response->json('sub'));
        $this->assertSame($user->name, $response->json('name'));
        $this->assertSame($user->profile_photo_url, $response->json('picture'));
        $this->assertSame($user->email, $response->json('email'));
        $this->assertTrue($response->json('email_verified'));
    }
}
