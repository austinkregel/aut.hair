<?php

namespace App\Models;

use App\Models\Contracts\Ownable;
use App\Models\Traits\HasOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Social extends Model implements Ownable
{
    use HasFactory, LogsActivity, HasOwner;

    public $fillable = [
        'ownable_id',
        'ownable_type',
        'provider',
        'provider_id',
        'email',
        'token',
        'refresh_token',
        'expires_at',
        'avatar',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logOnly(['ownable_id', 'ownable_type', 'avatar', 'provider']);
    }
}
