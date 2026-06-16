<?php

declare(strict_types=1);

namespace App\Gaia;

/**
 * Single source of truth for the GAIA scopes openFyde/ChromeOS uses.
 *
 * These are the literal Google scope strings Chromium requests verbatim. They
 * were previously duplicated in config/openid.php (as tokens_can) and
 * config/gaia.php (the sign-in scope list); both now derive from here.
 */
final class GaiaScopes
{
    /**
     * GAIA scopes + their consent descriptions. Merged into openid.passport.tokens_can
     * so Passport/GaiaScopeRepository recognize them at grant time.
     */
    public const DESCRIPTIONS = [
        'https://www.google.com/accounts/OAuthLogin' => 'Sign in to your openFyde device',
        'https://www.googleapis.com/auth/chromesync' => 'Sync your openFyde browser and OS state',
        'https://www.googleapis.com/auth/userinfo.email' => 'See your email address (openFyde)',
        'https://www.googleapis.com/auth/userinfo.profile' => 'See your basic profile info (openFyde)',
        // Deferred (enterprise enrollment only; consumer sign-in skips DM):
        'https://www.googleapis.com/auth/chromeosdevicemanagement' => 'Enroll this device in management',
    ];

    /**
     * Scopes the sign-in authorization code / token requests: the standard OIDC
     * identity scopes plus the GAIA ones the device needs (sign-in + sync).
     * (DM enrollment is omitted here — consumer sign-in doesn't request it.)
     */
    public const SIGN_IN = [
        'openid',
        'email',
        'https://www.google.com/accounts/OAuthLogin',
        'https://www.googleapis.com/auth/chromesync',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];
}
