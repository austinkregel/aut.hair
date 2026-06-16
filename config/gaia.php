<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | openFyde (ChromeOS) GAIA-compat config
    |--------------------------------------------------------------------------
    |
    | The confidential "ChromeOS" Passport client openFyde signs in against, and
    | the scopes its sign-in token carries. Run `php artisan cros:setup` to
    | provision the client, then set GAIA_CHROMEOS_CLIENT_ID here (and the same
    | id/secret in openFyde/auth.env on the build host). See the fyde-fork repo's
    | docs/gaia-shim-spike.md.
    */

    // The confidential ChromeOS client id (from `cros:setup`). The sign-in page
    // mints authorization codes for this client; oauth2/v4/token redeems them.
    'client_id' => env('GAIA_CHROMEOS_CLIENT_ID'),

    // Registered redirect_uri for that client. The device's code exchange aligns
    // with this; the exact value Chromium uses is finalized during on-device
    // validation.
    'redirect_uri' => env('GAIA_CHROMEOS_REDIRECT_URI', 'https://chromeos.localhost/oauth2/callback'),

    // Scopes minted for the sign-in token — the literal GAIA scope strings
    // Chromium requests. Kept in sync with config/openid.php `tokens_can`.
    'scopes' => [
        'openid',
        'email',
        'https://www.google.com/accounts/OAuthLogin',
        'https://www.googleapis.com/auth/chromesync',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ],
];
