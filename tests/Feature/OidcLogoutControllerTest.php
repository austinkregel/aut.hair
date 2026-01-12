<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Tests\TestCase;

class OidcLogoutControllerTest extends TestCase
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

    public function test_logout_logs_out_user_and_blacklists_token()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'redirect' => 'http://localhost/callback',
            'post_logout_redirect_uris' => 'http://localhost/logout-redirect',
        ]);
        $this->be($user);

        $jwt = $this->encodeSignedJwt([
            'sub' => $user->id,
            'aud' => $client->id,
            'jti' => 'test-jti-123',
            'exp' => time() + 3600,
            'iss' => config('app.url'),
        ]);

        $response = $this->post('/oauth/logout', [
            'id_token_hint' => $jwt,
        ]);
        $response->assertStatus(200);
        $response->assertSee('You have been logged out');

        // Assert token is blacklisted in cache
        $this->assertTrue(\Cache::has('oidc_token_blacklist:test-jti-123'));
    }

    public function test_logout_redirects_to_post_logout_redirect_uri_if_valid()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'redirect' => 'http://localhost/callback',
            'post_logout_redirect_uris' => 'http://localhost/logout-redirect',
        ]);
        $this->be($user);

        $jwt = $this->encodeSignedJwt([
            'sub' => $user->id,
            'aud' => $client->id,
            'jti' => 'test-jti-456',
            'exp' => time() + 3600,
            'iss' => config('app.url'),
        ]);

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

        $jwt = $this->encodeSignedJwt([
            'sub' => $user->id,
            'aud' => $client->id,
            'jti' => 'test-jti-789',
            'exp' => time() + 3600,
            'iss' => config('app.url'),
        ]);

        $response = $this->post('/oauth/logout', [
            'id_token_hint' => $jwt,
            'post_logout_redirect_uri' => 'http://malicious.com/evil',
        ]);
        $response->assertStatus(200);
        $response->assertSee('You have been logged out');
        $this->assertTrue(\Cache::has('oidc_token_blacklist:test-jti-789'));
    }

    private function encodeSignedJwt(array $payload): string
    {
        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::file(config('passport.private_key')),
            InMemory::file(config('passport.public_key'))
        );

        $builder = $config->builder()
            ->issuedBy($payload['iss'] ?? config('app.url'))
            ->permittedFor((string) $payload['aud'])
            ->identifiedBy($payload['jti'])
            ->relatedTo((string) $payload['sub'])
            ->issuedAt(new \DateTimeImmutable)
            ->expiresAt((new \DateTimeImmutable)->setTimestamp($payload['exp']));

        // Add any non-registered claims
        foreach ($payload as $key => $value) {
            if (in_array($key, ['iss', 'aud', 'jti', 'sub', 'exp'], true)) {
                continue;
            }
            $builder = $builder->withClaim($key, $value);
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }
}
