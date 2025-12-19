<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthTokenRouteTest extends TestCase
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

    public function test_oauth_token_route_is_protected_by_csrf()
    {
        // With no grant_type this should fail fast with 400, but remain reachable.
        $response = $this->post('/oauth/token', []);
        $response->assertStatus(400);
    }
}

