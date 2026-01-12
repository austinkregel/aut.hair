<?php

namespace App\Providers;

use App\Repositories\KeyRepositoryContract;
use App\Services\Auth\OidcIdTokenResponse;
use DateInterval;
use Illuminate\Encryption\Encrypter;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Passport as LaravelPassport;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use OpenIDConnect\ClaimExtractor;
use OpenIDConnect\Grant\AuthCodeGrant;
use OpenIDConnect\Laravel\LaravelCurrentRequestService;

class OidcPassportServiceProvider extends \OpenIDConnect\Laravel\PassportServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Enable OAuth2 client_credentials for machine-to-machine access.
        // Use afterResolving so we don't force AuthorizationServer construction before tests
        // override passport key config (construction requires valid keys).
        $this->app->afterResolving(AuthorizationServer::class, function (AuthorizationServer $server) {
            $server->enableGrantType(
                $this->buildClientCredentialsGrant(),
                new DateInterval('PT1H'),
            );
        });
    }

    public function makeAuthorizationServer(): AuthorizationServer
    {
        // Normalize key configuration (path vs file:// vs PEM contents) so we don't
        // fail with "Invalid key supplied" when env vars include file:// or relative paths.
        $privateCryptKey = $this->makeNormalizedCryptKey('private');
        $publicCryptKey = $this->makeNormalizedCryptKey('public');
        $encryptionKey = app(Encrypter::class)->getKey();
        $signer = app(config('openid.signer'));
        $keyRepository = app(KeyRepositoryContract::class);

        $kid = rtrim(strtr(
            base64_encode(hash('sha256', $keyRepository->getPublicKeyPem(), true)),
            '+/',
            '-_'
        ), '=');

        $responseType = new OidcIdTokenResponse(
            app(config('openid.repositories.identity')),
            app(ClaimExtractor::class),
            Configuration::forAsymmetricSigner(
                $signer,
                InMemory::plainText($privateCryptKey->getKeyContents()),
                InMemory::plainText($publicCryptKey->getKeyContents()),
            ),
            app(LaravelCurrentRequestService::class),
            $encryptionKey,
            $kid,
        );

        $server = new AuthorizationServer(
            app(ClientRepository::class),
            app(AccessTokenRepository::class),
            app(config('openid.repositories.scope')),
            $privateCryptKey,
            $encryptionKey,
            $responseType,
        );

        $server->enableGrantType(
            $this->buildAuthCodeGrant(),
            LaravelPassport::tokensExpireIn()
        );

        $server->enableGrantType(
            $this->buildRefreshTokenGrant(),
            LaravelPassport::refreshTokensExpireIn()
        );

        return $server;
    }

    protected function buildAuthCodeGrant()
    {
        return new AuthCodeGrant(
            $this->app->make(\Laravel\Passport\Bridge\AuthCodeRepository::class),
            $this->app->make(\Laravel\Passport\Bridge\RefreshTokenRepository::class),
            new \DateInterval('PT10M'),
            new \Nyholm\Psr7\Response,
            $this->app->make(LaravelCurrentRequestService::class),
        );
    }

    protected function buildClientCredentialsGrant(): ClientCredentialsGrant
    {
        return new ClientCredentialsGrant;
    }

    /**
     * Build a CryptKey from either PEM contents, a plain path, or a file:// URI.
     * Always passes an absolute path (no file:// prefix) to CryptKey when using files.
     */
    private function makeNormalizedCryptKey(string $type): CryptKey
    {
        $raw = (string) config('passport.'.$type.'_key', '');
        $raw = str_replace('\\n', "\n", $raw);

        // If config contains PEM contents, pass through.
        if (str_contains($raw, '-----BEGIN')) {
            return new CryptKey($raw, null, false);
        }

        // Default to Passport's key path (absolute).
        if ($raw === '') {
            $raw = Passport::keyPath('oauth-'.$type.'.key');
        }

        // Normalize file:// URIs to filesystem paths.
        if (str_starts_with($raw, 'file://')) {
            $raw = substr($raw, strlen('file://'));
        }

        // Normalize relative paths (e.g. storage/oauth-private.key) to absolute.
        if ($raw !== '' && ! str_starts_with($raw, '/')) {
            $raw = base_path($raw);
        }

        return new CryptKey($raw, null, false);
    }

    protected function buildRefreshTokenGrant(): RefreshTokenGrant
    {
        $grant = new RefreshTokenGrant($this->app->make(\Laravel\Passport\Bridge\RefreshTokenRepository::class));
        $grant->setRefreshTokenTTL(LaravelPassport::refreshTokensExpireIn());

        return $grant;
    }
}
