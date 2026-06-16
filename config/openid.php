<?php

declare(strict_types=1);

return [
    'passport' => [

        /**
         * Place your Passport and OpenID Connect scopes here.
         * To receive an `id_token, you should at least provide the openid scope.
         */
        'tokens_can' => [
            'openid' => 'Enable OpenID Connect',
            'name' => 'Can access your name',
            'profile' => 'Information about your profile',
            'email' => 'Information about your email address',
            'address' => 'Information about your address',
            'sync' => 'Release your sync-chain seed for self-hosted device sync',
            // 'login' => 'See your login information',

            // ChromeOS / GAIA-compat scopes. openFyde's Chromium requests these
            // literal Google scope strings verbatim during sign-in, Chrome Sync,
            // and device management (it speaks GAIA, repointed at us via
            // --fydeos-gaia-url/--fydeos-apis-url). Registered so Passport will
            // mint tokens for them. See the fyde-fork repo's docs/gaia-shim-spike.md.
            'https://www.google.com/accounts/OAuthLogin' => 'Sign in to your openFyde device',
            'https://www.googleapis.com/auth/chromesync' => 'Sync your openFyde browser and OS state',
            'https://www.googleapis.com/auth/userinfo.email' => 'See your email address (openFyde)',
            'https://www.googleapis.com/auth/userinfo.profile' => 'See your basic profile info (openFyde)',
            // Deferred (enterprise enrollment only; consumer sign-in skips DM):
            'https://www.googleapis.com/auth/chromeosdevicemanagement' => 'Enroll this device in management',
        ],
    ],

    /**
     * Place your custom claim sets here.
     */
    'custom_claim_sets' => [
        // 'login' => [
        //     'last-login',
        // ],
        // 'company' => [
        //     'company_name',
        //     'company_address',
        //     'company_phone',
        //     'company_email',
        // ],
    ],

    /**
     * You can override the repositories below.
     */
    'repositories' => [
        'identity' => \OpenIDConnect\Repositories\IdentityRepository::class,
        // GaiaScopeRepository extends the OIDC one to also accept the GAIA
        // scopes (from tokens_can above) — the vendor repo hardcodes only the
        // 5 OIDC scopes and would reject chromesync/OAuthLogin/etc.
        'scope' => \App\Repositories\GaiaScopeRepository::class,
    ],

    /**
     * The signer to be used
     * Can be Ecdsa, Hmac or RSA
     */
    'signer' => \Lcobucci\JWT\Signer\Rsa\Sha256::class,
    'routes' => [
        /**
         * When set to true, this package will expose the OpenID Connect Discovery endpoint.
         *  - /.well-known/openid-configuration
         */
        'discovery' => false,
        /**
         * When set to true, this package will expose the JSON Web Key Set endpoint.
         * - /oauth/jwks
         */
        'jwks' => false,
    ]
];
