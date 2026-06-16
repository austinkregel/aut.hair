<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A hashed reference to a token issued during a ChromeOS device sign-in.
 *
 * We never store the raw token — only its sha256 hash plus the `jti`
 * (== oauth_access_tokens.id for access tokens), which is what an admin revoke
 * targets. Append-only: refresh rotation issues new access tokens over time.
 */
class ChromeosDeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'chromeos_device_id',
        'jti',
        'token_hash',
        'type',
        'code_hash',
        'revoked',
        'issued_at',
    ];

    protected $casts = [
        'revoked' => 'boolean',
        'issued_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(ChromeosDevice::class, 'chromeos_device_id');
    }
}
