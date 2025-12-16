<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OidcUserinfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_userinfo_requires_openid_scope()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['profile']); // missing openid

        $response = $this->getJson(route('oidc.userinfo'));

        $response->assertStatus(403);
        $response->assertJson(['error' => 'insufficient_scope']);
    }

    public function test_userinfo_returns_claims_for_scoped_request()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['openid', 'profile', 'email']);

        $response = $this->getJson(route('oidc.userinfo'));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'sub' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $response->assertJsonStructure(['sub', 'name', 'picture', 'email', 'email_verified']);
    }
}

