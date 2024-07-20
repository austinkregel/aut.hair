<?php

namespace App\Models;

use App\Models\Contracts\CrudContract;
use App\Models\Contracts\Owner;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PersonalAccessTokenResult;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable implements CrudContract, MustVerifyEmail, Owner
{
    use CausesActivity, HasApiTokens, LogsActivity, TwoFactorAuthenticatable {
        createToken as createPassportToken;
    }
    use HasFactory, HasProfilePhoto, HasTeams, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'ownable_type',
        'ownable_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function socials()
    {
        return $this->morphMany(Social::class, 'ownable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function createToken(string $name, array $scopes = [])
    {
        $token = $this->createPassportToken($name, $scopes);

        return new class($token)
        {
            public function __construct(
                public PersonalAccessTokenResult $token,
                public $plainTextToken = null,
            ) {
                $this->plainTextToken = implode('|', ['part1', $token->accessToken]);
            }
        };
    }
}
