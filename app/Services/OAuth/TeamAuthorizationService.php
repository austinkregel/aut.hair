<?php

namespace App\Services\OAuth;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Passport\Client;

class TeamAuthorizationService
{
    public function userCanAccessClient(User $user, Client $client): bool
    {
        if (! $client->team_id) {
            return false;
        }

        return $this->getUserTeamsWithAccess($user, $client)->isNotEmpty();
    }

    public function getUserTeamsWithAccess(User $user, Client $client): Collection
    {
        if (! $client->team_id) {
            return collect();
        }

        return $user->allTeams()->filter(
            fn (Team $team) => $team->canAccessOAuthClient($client->id)
        );
    }

    public function getUserPermissionsForClient(User $user, Client $client): array
    {
        $permissions = [];

        foreach ($this->getUserTeamsWithAccess($user, $client) as $team) {
            $permissions = array_merge($permissions, $team->getEffectivePermissionsForClient($client->id));
        }

        return array_values(array_unique($permissions));
    }

    public function getUserPermissionsForClientAndTeam(User $user, Client $client, Team $team): array
    {
        if (! $user->belongsToTeam($team)) {
            return [];
        }

        if (! $team->canAccessOAuthClient($client->id)) {
            return [];
        }

        return $team->getEffectivePermissionsForClient($client->id);
    }
}
