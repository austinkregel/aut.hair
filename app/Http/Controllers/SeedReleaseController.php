<?php

namespace App\Http\Controllers;

use App\Models\SyncSeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Seed-release endpoint — the linchpin of identity-gated sync.
 *
 * Returns the authenticated user's sync-chain seed, generating it on first use.
 * The device turns this seed into (a) the go-sync self-signed auth token and
 * (b) the client-side encryption key — and, in the final phase, the cryptohome
 * disk key. The same user yields the same seed on every device, which is what
 * makes "powerwash -> sign in -> fully-synced slate" work.
 *
 * Gated by the `sync` scope (mirrors UserinfoController's `openid` check). The
 * sync wire itself is NOT protected here — it's network-isolated (the seed is
 * the sync identity, not an OAuth token).
 *
 * The seed is 128 bits of CSPRNG entropy, returned as hex. BIP39 mnemonic
 * encoding is a lossless re-encoding of this entropy and can be applied by the
 * device (or layered on here behind a BIP39 library) without changing the
 * stored material.
 */
class SeedReleaseController extends Controller
{
    /**
     * 128-bit seed = 16 bytes of entropy (BIP39's standard 12-word strength).
     */
    private const SEED_BYTES = 16;

    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->tokenCan('sync')) {
            return response()->json(['error' => 'insufficient_scope'], 403);
        }

        $syncSeed = SyncSeed::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['seed' => bin2hex(random_bytes(self::SEED_BYTES))],
        );

        return response()->json([
            'seed' => $syncSeed->seed,
            'encoding' => 'hex',
            'bits' => self::SEED_BYTES * 8,
        ]);
    }
}
