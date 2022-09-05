<?php

$response = [
    'authorization_endpoint' => route('passport.authorizations.authorize', []),
    'token_endpoint' => route('passport.token', []),
    'userinfo_endpoint' => route('oidc.userinfo', []),
    'jwks_uri' => route('oidc.jwks', []),
    'revocation_endpoint' => route('passport.authorizations.deny'),
    // 'service_documentation' => url('/'),
    'issuer' => url('/'),
    // 'end_session_endpoint' => route('oidc.logout', []),
    // 'introspection_endpoint' => route('oauth.introspect'),


    'response_types_supported' => ['code', 'token', 'id_token', 'code token', 'id_token token'],
    'acr_values_supported' => ['urn:mace:incommon:iap:gold', 'urn:mace:incommon:iap:silver', 'urn:mace:incommon:iap:bronze'],
    'scopes_supported' => ['openid', 'profile', 'email','profile', 'email', 'name'],
    'claims_supported' => ['sub', 'iss', 'roles', 'acr', 'picture', 'profile'],
    'code_challenge_methods_supported' => ['S256'],
    'introspection_endpoint_auth_methods_supported' => ['client_secret_jwt'],
    'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
    'ui_locales_supported' => ['en-GB'],
];
?>
{!! str_replace('http://', 'https://', json_encode($response,JSON_PRETTY_PRINT)) !!}