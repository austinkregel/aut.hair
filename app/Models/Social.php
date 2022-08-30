<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Social extends Model
{
    use HasFactory, LogsActivity;

    public $fillable = [
        'user_id',
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
            ->logOnly(['user_id', 'avatar', 'provider']);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
