<?php

namespace App\Models;

use App\Models\Contracts\CrudContract;
use App\Models\Contracts\Owner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Team as JetstreamTeam;
use Laravel\Passport\Client;

class Team extends JetstreamTeam implements CrudContract, Owner
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'personal_team' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    public function oauthClients(): HasMany
    {
        return $this->hasMany(Client::class, 'team_id');
    }

    public function invitedTeams(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'oauth_client_team_invitations',
            'inviting_team_id',
            'invited_team_id'
        )->withPivot(['oauth_client_id', 'role'])->withTimestamps();
    }

    public function invitingTeams(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'oauth_client_team_invitations',
            'invited_team_id',
            'inviting_team_id'
        )->withPivot(['oauth_client_id', 'role'])->withTimestamps();
    }

    public function canAccessOAuthClient(int|string $clientId): bool
    {
        if ($this->oauthClients()->where('id', $clientId)->exists()) {
            return true;
        }

        return $this->invitingTeams()
            ->wherePivot('oauth_client_id', $clientId)
            ->exists();
    }

    public function getEffectivePermissionsForClient(int|string $clientId): array
    {
        $permissions = [];

        // Owner of the client: treat as admin-level for this app.
        $adminRole = Jetstream::findRole('admin');
        if ($this->oauthClients()->where('id', $clientId)->exists() && $adminRole) {
            $permissions = array_merge($permissions, $adminRole->permissions);
        }

        // Permissions from invitation role.
        $invitingTeam = $this->invitingTeams()
            ->wherePivot('oauth_client_id', $clientId)
            ->first();

        if ($invitingTeam && $invitingTeam->pivot?->role) {
            $role = Jetstream::findRole($invitingTeam->pivot->role);
            if ($role) {
                $permissions = array_merge($permissions, $role->permissions);
            }
        }

        // Permissions inherited from member roles.
        foreach ($this->users as $member) {
            $memberRole = $member->membership->role ?? null;
            if ($memberRole) {
                $role = Jetstream::findRole($memberRole);
                if ($role) {
                    $permissions = array_merge($permissions, $role->permissions);
                }
            }
        }

        return array_values(array_unique($permissions));
    }
}
