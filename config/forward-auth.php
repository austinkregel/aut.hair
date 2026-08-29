<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verify endpoint rate limit
    |--------------------------------------------------------------------------
    |
    | Requests per minute, per client IP, allowed against the forward-auth verify
    | endpoint (/outpost.goauthentik.io/auth/nginx). It is publicly reachable and
    | unauthenticated, so this caps abuse — including the auto-discovery row writes
    | below. Tune up if a single busy client legitimately exceeds it.
    |
    */
    'throttle' => (int) env('FORWARD_AUTH_THROTTLE', 30),

    /*
    |--------------------------------------------------------------------------
    | First-contact auto-discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, an unknown host seen at the verify endpoint is auto-registered
    | as a pending ProxyApp for approval. To stop random internet actors from
    | flooding the approval queue with junk rows, discovery only happens when the
    | resolved client IP falls inside one of `trusted_subnets`. Unknown hosts from
    | anywhere else still fail closed (403) — they just do not create a row.
    |
    | Defaults match the private/Docker ranges homelab-in-a-box uses for its
    | `private` ingress middleware.
    |
    */
    'auto_discovery' => (bool) env('FORWARD_AUTH_AUTO_DISCOVERY', true),

    'trusted_subnets' => array_filter(array_map('trim', explode(',', (string) env(
        'FORWARD_AUTH_TRUSTED_SUBNETS',
        '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'
    )))),

];
