<?php

declare(strict_types=1);

namespace App\Gaia;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Stable, opaque, non-enumerable GAIA id for a user.
 *
 * Replaces exposing the sequential primary key as the `obfuscatedid`/`id` (which
 * is enumerable; Google's gaia id is opaque). Derived via HMAC over the user's
 * key with APP_KEY, so it is:
 *   - stable per user (same on every device/sign-in),
 *   - opaque / non-enumerable,
 *   - not a database round-trip and needs no migration.
 *
 * Stability is tied to APP_KEY, which is already part of the trust base (it also
 * encrypts the sync seed) — rotating APP_KEY resets derived identity, same as it
 * resets the seed.
 *
 * MUST be used identically for the `google-accounts-signin` header obfuscatedid
 * and the userinfo `id` so the two stay coupled.
 */
final class GaiaIdentity
{
    public static function for(Authenticatable $user): string
    {
        return hash_hmac('sha256', (string) $user->getAuthIdentifier(), (string) config('app.key'));
    }
}
