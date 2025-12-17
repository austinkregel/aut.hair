<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Services\OAuth\TeamAuthorizationService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Laravel\Passport\Client;

class OAuthClientPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, ?Team $team = null): bool
    {
        return $team ? $user->belongsToTeam($team) : true;
    }

    public function view(User $user, Client $client): bool
    {
        if (! $client->team_id) {
            return false;
        }

        $team = Team::find($client->team_id);
        if (! $team) {
            return false;
        }

        $service = app(TeamAuthorizationService::class);

        return $service->userCanAccessClient($user, $client);
    }

    public function create(User $user, ?Team $team = null): bool
    {
        return $team ? $user->ownsTeam($team) : true;
    }

    public function update(User $user, Client $client, ?Team $team = null): bool
    {
        $team = $team ?: ($client->team_id ? Team::find($client->team_id) : null);

        if (! $team) {
            return false;
        }

        // Prevent "client stealing" by requiring the client to belong to the team being managed.
        if ((int) $client->team_id !== (int) $team->id) {
            return false;
        }

        return $user->ownsTeam($team);
    }

    public function delete(User $user, Client $client, ?Team $team = null): bool
    {
        return $this->update($user, $client, $team);
    }

    public function inviteTeam(User $user, Client $client, Team $invitingTeam): bool
    {
        return $user->ownsTeam($invitingTeam) && (int) $client->team_id === (int) $invitingTeam->id;
    }

    public function removeTeam(User $user, Client $client, Team $invitingTeam): bool
    {
        return $this->inviteTeam($user, $client, $invitingTeam);
    }
}

