<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An openFyde/ChromeOS device that has signed in via the GAIA flow.
 *
 * Identified by the server-set `device_id` cookie — a best-effort SOFT identity
 * (it resets on powerwash), not a hardware id. Real device identity would come
 * from DM enrollment, which is out of scope. Used for audit, the admin device
 * list, and correlating issued tokens to the device via `last_code_hash`.
 */
class ChromeosDevice extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'device_id',
        'team_id',
        'user_id',
        'last_code_hash',
        'last_seen_ip',
        'last_user_agent',
        'last_seen_at',
        'approved',
    ];

    protected $casts = [
        'approved' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logOnly(['device_id', 'team_id', 'user_id', 'approved']);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ChromeosDeviceToken::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
