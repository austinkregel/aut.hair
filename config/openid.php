<?php

declare(strict_types=1);

return [
    'passport' => [

        /**
         * Place your Passport and OpenID Connect scopes here.
         * To receive an `id_token, you should at least provide the openid scope.
         */
        'tokens_can' => array_merge([
            'openid' => 'Enable OpenID Connect',
            'name' => 'Can access your name',
            'profile' => 'Information about your profile',
            'email' => 'Information about your email address',
            'address' => 'Information about your address',
            'sync' => 'Release your sync-chain seed for self-hosted device sync',
            // 'login' => 'See your login information',
        ],
            // ChromeOS / GAIA-compat scopes — the literal Google scope strings
            // openFyde's Chromium requests verbatim (it speaks GAIA, repointed at
            // us via --fydeos-gaia-url/--fydeos-apis-url). Single source of truth
            // in App\Gaia\GaiaScopes (shared with config/gaia.php). Registered
            // here so Passport/GaiaScopeRepository mint tokens for them.
            \App\Gaia\GaiaScopes::DESCRIPTIONS
        ),
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
