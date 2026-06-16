<?php

declare(strict_types=1);

namespace App\Repositories;

use OpenIDConnect\Entities\ScopeEntity;
use OpenIDConnect\Repositories\ScopeRepository as BaseScopeRepository;

/**
 * Scope repository that recognizes the GAIA scopes openFyde/ChromeOS requests
 * (e.g. https://www.googleapis.com/auth/chromesync, .../OAuthLogin) on top of
 * the standard OIDC scopes.
 *
 * The vendor ScopeRepository hardcodes only {openid, profile, email, phone,
 * address} and ignores config, so any GAIA scope is rejected as "invalid scope"
 * at grant time — which would break both our sign-in code mint and Chromium's
 * own chromesync token requests. We instead accept anything registered in
 * config('openid.passport.tokens_can'), which is where the GAIA scopes are
 * declared (see config/openid.php). Wired in via config('openid.repositories.scope').
 */
class GaiaScopeRepository extends BaseScopeRepository
{
    public function getScopeEntityByIdentifier($identifier)
    {
        $allowed = array_merge(
            ['openid', 'profile', 'email', 'phone', 'address'],
            array_keys((array) config('openid.passport.tokens_can', []))
        );

        if (! in_array($identifier, $allowed, true)) {
            return null;
        }

        $scope = new ScopeEntity();
        $scope->setIdentifier($identifier);

        return $scope;
    }
}
