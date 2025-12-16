<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OAuthTokenRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_token_route_is_protected_by_csrf()
    {
        // With no grant_type this should fail fast with 400, but remain reachable.
        $response = $this->post('/oauth/token', []);
        $response->assertStatus(400);
    }
}

