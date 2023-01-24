<?php

namespace App\Models;

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
        if ($this->expires_at) {
            return $this->expires_at->before(now());
        }

        return false;
    }
}
