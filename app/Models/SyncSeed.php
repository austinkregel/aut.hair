<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's sync-chain seed — the single shared secret behind self-hosted,
 * client-side-encrypted browser/OS sync (go-sync / Brave Sync v2 compatible).
 *
 * The seed is the sync identity: the device derives the signing keypair and the
 * client-side encryption (Nigori) key from it. aut.hair only gates *release* of
 * the seed (see SeedReleaseController); it never participates in the sync wire.
 *
 * Stored encrypted at rest via the `encrypted` cast (uses APP_KEY). APP_KEY is
 * therefore part of the trust base — back it up separately from the database.
 */
class SyncSeed extends Model
{
    protected $fillable = [
        'user_id',
        'seed',
    ];

    protected $casts = [
        'seed' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
