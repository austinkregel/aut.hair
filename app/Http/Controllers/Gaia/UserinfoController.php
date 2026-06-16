<?php

namespace App\Http\Controllers\Gaia;

use App\Gaia\GaiaIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GAIA UserInfo (`oauth2/v1/userinfo`) for openFyde sign-in.
 *
 * Chromium calls this with an access token to resolve the signed-in identity.
 * Returns the GAIA shape Chromium parses: `id` (the stable gaia id, which must
 * match the `obfuscatedid` emitted in the `google-accounts-signin` header at
 * sign-in), `email`, and `verified_email`.
 *
 * We deliberately OMIT `hostedDomain`: its absence makes Chromium treat the
 * account as a consumer account and skip enterprise device-management
 * enrollment (see the fyde-fork repo's docs/gaia-shim-spike.md). Add it back
 * only if managed enrollment is wanted.
 *
 * Mirrors the invokable, `auth:api`-gated style of UserinfoController /
 * SeedReleaseController.
 */
class UserinfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        return response()->json([
            // Opaque, stable gaia id — coupled with the obfuscatedid emitted in
            // the sign-in header (App\Gaia\GaiaIdentity). NOT the sequential PK.
            'id' => GaiaIdentity::for($user),
            'email' => $user->email,
            'verified_email' => (bool) $user->email_verified_at,
        ]);
    }
}
