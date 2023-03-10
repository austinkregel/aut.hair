<?php

namespace App\Models;

use Carbon\Carbon;
/** @property Carbon $expires_at */

class Token extends \Laravel\Passport\Token
{
    protected $fillable = [
        'id',
        'client_id',
        'name',
        'scopes',
        'revoked',
        'expires_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'revoked' => 'bool',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public $appends = [
        'abilities',
        'token',
        'plainTextToken',
    ];

    public function setAbilitiesAttribute($value)
    {
        $this->scopes = $value;
    }

    public function getTokenAttribute()
    {
        return $this->id;
    }

    public function setTokenAttribute($value)
    {
        $this->id = $value;
    }

    public function getAbilitiesAttribute()
    {
        return $this->scopes;
    }

    public function getRevokedAttribute()
    {
        if (!empty($this->expires_at)) {
            return $this->expires_at->isBefore(now());
        }

        return false;
    }

    public function getPlainTextTokenAttribute()
    {
        return $this->id;
    }
}
