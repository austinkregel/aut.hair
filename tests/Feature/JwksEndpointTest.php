<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class JwksEndpointTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test the /api/jwks endpoint returns the correct JWKS structure and values for a known public key.
     *
     * @return void
     */
    public function test_jwks_endpoint_returns_expected_jwk()
    {
        // Act: Call the endpoint
        $response = $this->getJson(route('oidc.jwks'));

        // Assert: Structure and values
        $response->assertStatus(200)
            ->assertJsonStructure([
                'keys' => [
                    [
                        'kty', 'alg', 'use', 'n', 'e', 'kid',
                    ],
                ],
            ]);
        $jwk = $response->json('keys')[0];
        $this->assertEquals('RSA', $jwk['kty']);
        $this->assertEquals('RS256', $jwk['alg']);
        $this->assertEquals('sig', $jwk['use']);
        $this->assertEquals('laravel-passport', $jwk['kid']);
        // Check that modulus and exponent are non-empty base64url strings
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $jwk['n']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $jwk['e']);
    }
}
