<?php

namespace App\Services\Auth;

use Lcobucci\JWT\Builder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use OpenIDConnect\Interfaces\IdentityEntityInterface;
use OpenIDConnect\IdTokenResponse;

class OidcIdTokenResponse extends IdTokenResponse
{
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
}

