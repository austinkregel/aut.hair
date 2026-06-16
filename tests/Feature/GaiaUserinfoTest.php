<?php

namespace Tests\Feature;

use App\Gaia\GaiaIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * GAIA UserInfo endpoint (oauth2/v1/userinfo) — the identity Chromium reads
 * during openFyde sign-in. Mirrors SyncSeedEndpointTest's conventions.
 */
class GaiaUserinfoTest extends TestCase
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

    public function test_userinfo_requires_a_valid_token(): void
    {
        $this->getJson(route('gaia.userinfo'))->assertStatus(401);
    }

    public function test_userinfo_returns_the_gaia_shape(): void
    {
        $user = User::factory()->create([
            'email' => 'user@aut.hair',
            'email_verified_at' => now(),
        ]);

        // Chromium presents a token with the userinfo.email scope.
        Passport::actingAs($user, ['https://www.googleapis.com/auth/userinfo.email']);

        $response = $this->getJson(route('gaia.userinfo'));

        $response->assertStatus(200);
        $response->assertExactJson([
            'id' => GaiaIdentity::for($user),
            'email' => 'user@aut.hair',
            'verified_email' => true,
        ]);

        // The id must be opaque, not the enumerable primary key.
        $this->assertNotEquals((string) $user->id, $response->json('id'));
    }

    public function test_userinfo_omits_hosted_domain_so_consumer_skips_dm(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Passport::actingAs($user, ['openid']);

        $response = $this->getJson(route('gaia.userinfo'));

        $response->assertStatus(200);
        // No hostedDomain => Chromium treats this as a consumer account and does
        // NOT attempt enterprise device-management enrollment.
        $response->assertJsonMissingPath('hostedDomain');
    }

    public function test_unverified_email_is_reported_as_such(): void
    {
        $user = User::factory()->unverified()->create();

        Passport::actingAs($user, ['https://www.googleapis.com/auth/userinfo.email']);

        $this->getJson(route('gaia.userinfo'))
            ->assertStatus(200)
            ->assertJson(['verified_email' => false]);
    }
}
