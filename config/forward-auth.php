<?php

return [

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

    /*
    |--------------------------------------------------------------------------
    | Discovery rate limit
    |--------------------------------------------------------------------------
    |
    | New-host discovery attempts per minute, per client IP. This guards ONLY the
    | row-creating discovery branch — the verify hot path for already-registered
    | apps is never throttled, so asset-heavy page loads are unaffected. Discovering
    | a new app is a rare, once-per-app event, so this can stay tight.
    |
    */
    'discovery_throttle' => (int) env('FORWARD_AUTH_DISCOVERY_THROTTLE', 20),

    'trusted_subnets' => array_filter(array_map('trim', explode(',', (string) env(
        'FORWARD_AUTH_TRUSTED_SUBNETS',
        '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'
    )))),

];
