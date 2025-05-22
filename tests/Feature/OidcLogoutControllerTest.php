<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Tests\TestCase;

class OidcLogoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create the blacklist table if it doesn't exist (for test DB)
        if (! DB::getSchemaBuilder()->hasTable('oidc_token_blacklist')) {
            DB::getSchemaBuilder()->create('oidc_token_blacklist', function ($table) {
                $table->string('jti')->unique();
                $table->timestamp('revoked_at')->nullable();
            });
        }
    }

    public function test_logout_logs_out_user_and_blacklists_token()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'redirect' => 'http://localhost/callback',
            'post_logout_redirect_uris' => 'http://localhost/logout-redirect',
        ]);
        $this->be($user);

        // Create a fake id_token_hint (JWT) with jti and aud (client id)
        $payload = [
            'sub' => $user->id,
            'aud' => $client->id,
            'jti' => 'test-jti-123',
            'exp' => time() + 3600,
        ];
        $jwt = $this->encodeFakeJwt($payload);

        $response = $this->post('/oauth/logout', [
            'id_token_hint' => $jwt,
        ]);
        $response->assertStatus(200);
        $response->assertSee('You have been logged out');

        // Assert token is blacklisted
        $this->assertDatabaseHas('oidc_token_blacklist', [
            'jti' => 'test-jti-123',
        ]);
    }

    public function test_logout_redirects_to_post_logout_redirect_uri_if_valid()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'redirect' => 'http://localhost/callback',
            'post_logout_redirect_uris' => 'http://localhost/logout-redirect',
        ]);
        $this->be($user);

        $payload = [
            'sub' => $user->id,
            'aud' => $client->id,
            'jti' => 'test-jti-456',
            'exp' => time() + 3600,
        ];
        $jwt = $this->encodeFakeJwt($payload);

        $response = $this->post('/oauth/logout', [
            'id_token_hint' => $jwt,
            'post_logout_redirect_uri' => 'http://localhost/logout-redirect',
            'state' => 'xyz',
        ]);
        $response->assertStatus(302);
        $response->assertHeader('Location', 'http://localhost/logout-redirect?state=xyz');
    }

    public function test_logout_does_not_redirect_to_unregistered_uri()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'redirect' => 'http://localhost/callback',
            'post_logout_redirect_uris' => 'http://localhost/logout-redirect',
        ]);
        $this->be($user);

        $payload = [
            'sub' => $user->id,
            'aud' => $client->id,
            'jti' => 'test-jti-789',
            'exp' => time() + 3600,
        ];
        $jwt = $this->encodeFakeJwt($payload);

        $response = $this->post('/oauth/logout', [
            'id_token_hint' => $jwt,
            'post_logout_redirect_uri' => 'http://malicious.com/evil',
        ]);
        $response->assertStatus(200);
        $response->assertSee('You have been logged out');
        $this->assertDatabaseHas('oidc_token_blacklist', [
            'jti' => 'test-jti-789',
        ]);
    }

    private function encodeFakeJwt(array $payload): string
    {
        $header = base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $body = base64_encode(json_encode($payload));

        return rtrim(strtr($header, '+/', '-_'), '=').'.'.rtrim(strtr($body, '+/', '-_'), '=').'.';
    }
}
