<?php

namespace App\Providers;

use App\Repositories\KeyRepositoryContract;
use App\Services\Auth\OidcIdTokenResponse;
use Illuminate\Encryption\Encrypter;
use Laravel\Passport;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\Passport as LaravelPassport;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use OpenIDConnect\ClaimExtractor;
use OpenIDConnect\Grant\AuthCodeGrant;
use OpenIDConnect\Laravel\LaravelCurrentRequestService;

class OidcPassportServiceProvider extends \OpenIDConnect\Laravel\PassportServiceProvider
{
    public function makeAuthorizationServer(): AuthorizationServer
    {
        $privateCryptKey = $this->makeCryptKey('private');
        $publicCryptKey = $this->makeCryptKey('public');
        $encryptionKey = app(Encrypter::class)->getKey();
        $signer = app(config('openid.signer'));
        $keyRepository = app(KeyRepositoryContract::class);

        $kid = config('openid.kid') ?: hash('sha256', $keyRepository->getPublicKeyPem());

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
            $this->app->make(Passport\Bridge\AuthCodeRepository::class),
            $this->app->make(Passport\Bridge\RefreshTokenRepository::class),
            new \DateInterval('PT10M'),
            new \Nyholm\Psr7\Response(),
            $this->app->make(LaravelCurrentRequestService::class),
        );
    }

    protected function buildRefreshTokenGrant(): RefreshTokenGrant
    {
        $grant = new RefreshTokenGrant($this->app->make(Passport\Bridge\RefreshTokenRepository::class));
        $grant->setRefreshTokenTTL(LaravelPassport::refreshTokensExpireIn());

        return $grant;
    }
}

