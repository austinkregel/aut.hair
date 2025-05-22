<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WellKnownController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'issuer' => route('issuer'),
            'authorization_endpoint' => route('passport.authorizations.authorize', []),
            'token_endpoint' => route('passport.token', []),
            'userinfo_endpoint' => route('oidc.userinfo', []),
            'jwks_uri' => route('oidc.jwks', []),
            'revocation_endpoint' => route('passport.authorizations.deny'),
            'end_session_endpoint' => route('oauth.logout', []),
            // 'service_documentation' => route('docs'),
            'response_types_supported' => ['code', 'token', 'id_token', 'code token', 'id_token token'],
            'scopes_supported' => ['openid', 'profile', 'email', 'name'],
            'claims_supported' => ['sub', 'iss', 'roles', 'acr', 'picture', 'profile'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'private_key_jwt'],
            'ui_locales_supported' => ['en-US'],
            // Auth0 does this stuff, but I couldn't find any explicit reference to them in the defining spec.
            //        'device_authorization_endpoint' => 'https://auth.auth0.com/oauth/device/code',
            //        'mfa_challenge_endpoint' => 'https://auth.auth0.com/mfa/challenge',
        ]);
    }
}
