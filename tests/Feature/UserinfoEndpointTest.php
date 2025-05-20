<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UserinfoEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Point Passport to the test keys
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

    public function test_userinfo_endpoint_with_only_openid_scope()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        Passport::actingAs($user, ['openid']);

        $response = $this->getJson('/api/userinfo');

        $response->assertStatus(200)
            ->assertJsonStructure(['sub'])
            ->assertJsonMissing(['name', 'picture', 'email', 'email_verified']);
        $this->assertSame((string) $user->id, $response->json('sub'));
    }

    public function test_userinfo_endpoint_with_only_profile_scope()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        Passport::actingAs($user, ['profile']);

        $response = $this->getJson('/api/userinfo');

        $response->assertStatus(200)
            ->assertJsonStructure(['name', 'picture'])
            ->assertJsonMissing(['sub', 'email', 'email_verified']);
        $this->assertSame($user->name, $response->json('name'));
        $this->assertSame($user->profile_photo_url, $response->json('picture'));
    }

    public function test_userinfo_endpoint_with_only_email_scope()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        Passport::actingAs($user, ['email']);

        $response = $this->getJson('/api/userinfo');

        $response->assertStatus(200)
            ->assertJsonStructure(['email', 'email_verified'])
            ->assertJsonMissing(['sub', 'name', 'picture']);
        $this->assertSame($user->email, $response->json('email'));
        $this->assertTrue($response->json('email_verified'));
    }

    public function test_userinfo_endpoint_with_profile_and_email_scopes()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        Passport::actingAs($user, ['profile', 'email']);

        $response = $this->getJson('/api/userinfo');

        $response->assertStatus(200)
            ->assertJsonStructure(['name', 'picture', 'email', 'email_verified'])
            ->assertJsonMissing(['sub']);
        $this->assertSame($user->name, $response->json('name'));
        $this->assertSame($user->profile_photo_url, $response->json('picture'));
        $this->assertSame($user->email, $response->json('email'));
        $this->assertTrue($response->json('email_verified'));
    }
}
