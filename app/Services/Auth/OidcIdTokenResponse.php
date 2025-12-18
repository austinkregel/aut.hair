<?php

namespace App\Services\Auth;

use DateTimeImmutable;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Illuminate\Support\Facades\Session;
use OpenIDConnect\Interfaces\IdentityEntityInterface;
use OpenIDConnect\Interfaces\CurrentRequestServiceInterface;
use OpenIDConnect\IdTokenResponse;

class OidcIdTokenResponse extends IdTokenResponse
{
    protected Configuration $config;
    /**
     * Keep a local reference to the current request service to avoid dynamic property access issues.
     */
    protected ?CurrentRequestServiceInterface $currentRequestService = null;

    public function __construct(
        $identityRepository,
        $claimExtractor,
        $config,
        $currentRequestService,
        $encryptionKey,
        private string $kid
    ) {
        parent::__construct(
            $identityRepository,
            $claimExtractor,
            $config,
            $currentRequestService,
            $encryptionKey
        );

        $this->config = $config;
        $this->currentRequestService = $currentRequestService;
    }

    protected function getBuilder(
        AccessTokenEntityInterface $accessToken,
        IdentityEntityInterface $userEntity
    ): Builder {
        $builder = parent::getBuilder($accessToken, $userEntity);

        return $builder
            ->withHeader('kid', $this->kid)
            ->withHeader('alg', 'RS256');
    }

    /**
     * Include OIDC-required claims (nonce, auth_time, at_hash) without touching vendor code.
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        if (! $this->hasOpenIDScope(...$accessToken->getScopes())) {
            return [];
        }

        $grantType = null;
        if ($this->currentRequestService) {
            $grantType = $this->currentRequestService->getRequest()->getParsedBody()['grant_type'] ?? null;
        }

        // Only authorization_code and refresh_token flows issue ID tokens.
        if ($grantType && ! in_array($grantType, ['authorization_code', 'refresh_token'], true)) {
            return [];
        }

        $user = $this->identityRepository->getByIdentifier(
            (string) $accessToken->getUserIdentifier(),
        );

        $builder = $this->getBuilder($accessToken, $user);

        $claims = $this->claimExtractor->extract(
            $accessToken->getScopes(),
            $user->getClaims(),
        );

        foreach ($claims as $claimName => $claimValue) {
            $builder = $builder->withClaim($claimName, $claimValue);
        }

        $authCodePayload = [];
        if ($this->currentRequestService) {
            $body = $this->currentRequestService->getRequest()->getParsedBody();
            if (isset($body['code'])) {
                $authCodePayload = json_decode(
                    $this->decrypt($body['code']),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        }

        $nonce = $authCodePayload['nonce'] ?? Session::pull('oidc_nonce');
        if ($nonce) {
            $builder = $builder->withClaim('nonce', $nonce);
        }

        $authTime = isset($authCodePayload['auth_time'])
            ? (int) $authCodePayload['auth_time']
            : (int) Session::get('oidc_auth_time', (new DateTimeImmutable())->getTimestamp());

        $builder = $builder->withClaim('auth_time', $authTime);

        $token = $builder->getToken(
            $this->config->signer(),
            $this->config->signingKey(),
        );

        $extra = ['id_token' => $token->toString()];

        // Include at_hash when access token is issued alongside the ID token.
        $accessTokenString = method_exists($accessToken, '__toString') ? (string) $accessToken : null;
        if ($accessTokenString) {
            $hash = hash('sha256', $accessTokenString, true);
            $atHash = substr($hash, 0, 16); // left-most 128 bits
            $extra['at_hash'] = rtrim(strtr(base64_encode($atHash), '+/', '-_'), '=');
        }

        return $extra;
    }

    private function hasOpenIDScope(ScopeEntityInterface ...$scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($scope->getIdentifier() === 'openid') {
                return true;
            }
        }

        return false;
    }
}

