<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class OidcClientCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_client_credentials_issues_access_token_without_id_token()
    {
        $client = app(ClientRepository::class)->create(null, 'Machine Client', 'http://localhost');

        $response = $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'scope' => 'openid',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
        ]);
        $response->assertJsonMissing([
            'id_token',
        ]);
        $response->assertJsonMissing([
            'refresh_token',
        ]);
    }

    public function test_machine_info_requires_openid_scope()
    {
        $client = app(ClientRepository::class)->create(null, 'Machine Client', 'http://localhost');

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'scope' => '',
        ]);

        $tokenResponse->assertStatus(200);
        $accessToken = $tokenResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson(route('oidc.machine_info'));

        $response->assertStatus(403);
        $response->assertJson(['error' => 'insufficient_scope']);
    }

    public function test_machine_info_returns_client_metadata_for_scoped_request()
    {
        $client = app(ClientRepository::class)->create(null, 'Machine Client', 'http://localhost');

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'scope' => 'openid',
        ]);

        $tokenResponse->assertStatus(200);
        $accessToken = $tokenResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson(route('oidc.machine_info'));

        $response->assertStatus(200);
        $response->assertJson([
            'client_id' => (string) $client->id,
            'name' => 'Machine Client',
        ]);
        $response->assertJsonStructure(['client_id', 'name', 'scopes']);
    }

    public function test_client_credentials_rejects_invalid_client_secret()
    {
        $client = app(ClientRepository::class)->create(null, 'Machine Client', 'http://localhost');

        $response = $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => 'wrong',
            'scope' => 'openid',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'invalid_client',
        ]);
    }
}


