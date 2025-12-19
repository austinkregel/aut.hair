<?php

namespace App\Providers;

use App\Repositories\KeyRepositoryContract;
use App\Services\Auth\OidcIdTokenResponse;
use DateInterval;
use Illuminate\Encryption\Encrypter;
use Laravel\Passport;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\ClientRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
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
        $privateCryptKey = $this->makeCryptKey('private');
        $publicCryptKey = $this->makeCryptKey('public');
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
                InMemory::file($privateCryptKey->getKeyPath()),
                InMemory::file($publicCryptKey->getKeyPath()),
            ),
            app(LaravelCurrentRequestService::class),
            $encryptionKey,
            $kid,
        );

        return new AuthorizationServer(
            app(ClientRepository::class),
            app(AccessTokenRepository::class),
            app(config('openid.repositories.scope')),
            $privateCryptKey,
            $encryptionKey,
            $responseType,
        );
    }

    protected function buildAuthCodeGrant()
    {
        return new AuthCodeGrant(
            $this->app->make(Passport\Bridge\AuthCodeRepository::class),
            $this->app->make(Passport\Bridge\RefreshTokenRepository::class),
            new \DateInterval('PT10M'),
            new \Nyholm\Psr7\Response(),
            $this->app->make(LaravelCurrentRequestService::class),
        );
    }

    protected function buildClientCredentialsGrant(): ClientCredentialsGrant
    {
        return new ClientCredentialsGrant();
    }
}

