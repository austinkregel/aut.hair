<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WellKnownController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $issuer = rtrim(config('app.url'), '/');
        $scopes = array_keys(config('openid.passport.tokens_can', []));

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'userinfo_endpoint' => route('oidc.userinfo'),
            'machine_info_endpoint' => route('oidc.machine_info'),
            'jwks_uri' => route('oidc.jwks'),
            'revocation_endpoint' => route('oauth.revoke'),
            'end_session_endpoint' => route('oauth.logout'),
            'response_types_supported' => ['code'], // Passport does not expose implicit/hybrid
            'response_modes_supported' => ['query', 'fragment', 'form_post'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'scopes_supported' => $scopes,
            'id_token_signing_alg_values_supported' => ['RS256'],
            'subject_types_supported' => ['public'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => [
                'sub', 'name', 'email', 'email_verified', 'picture', 'updated_at',
            ],
        ]);
    }
}
