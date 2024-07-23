<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'enabled' => true,
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
    ],

    'github' => [
        'enabled' => true,
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT'),
    ],

    'synology' => [
        'enabled' => true,
        'client_id' => env('SYNOLOGY_CLIENT_ID'),
        'client_secret' => env('SYNOLOGY_CLIENT_SECRET'),
        'redirect' => env('SYNOLOGY_REDIRECT', 'localhost/callback/synology'),
    ],

    'discord' => [
        'enabled' => true,
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI'),
        'allow_gif_avatars' => (bool) env('DISCORD_AVATAR_GIF', true),
        'avatar_default_extension' => env('DISCORD_EXTENSION_DEFAULT', 'jpg'), // only pick from jpg, png, webp
    ],

    'laravelpassport' => [
        'client_id' => env('LARAVELPASSPORT_CLIENT_ID'),
        'client_secret' => env('LARAVELPASSPORT_CLIENT_SECRET'),
        'redirect' => env('LARAVELPASSPORT_REDIRECT_URI'),
        'host' => env('LARAVELPASSPORT_HOST'),
    ],

];
