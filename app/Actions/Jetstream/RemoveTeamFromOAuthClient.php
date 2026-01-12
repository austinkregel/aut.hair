<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Passport\Client;

class RemoveTeamFromOAuthClient
{
    public function remove($user, Team $invitingTeam, Team $invitedTeam, Client $client): void
    {
        Gate::forUser($user)->authorize('removeTeam', [$client, $invitingTeam]);

        $this->validate($invitingTeam, $invitedTeam, $client);

        DB::table('oauth_client_team_invitations')
            ->where('inviting_team_id', $invitingTeam->id)
            ->where('invited_team_id', $invitedTeam->id)
            ->where('oauth_client_id', $client->id)
            ->delete();
    }

    protected function validate(Team $invitingTeam, Team $invitedTeam, Client $client): void
    {
        Validator::make([
            'inviting_team_id' => $invitingTeam->id,
            'invited_team_id' => $invitedTeam->id,
            'oauth_client_id' => $client->id,
        ], [
            'inviting_team_id' => [
                'required',
                Rule::exists('teams', 'id'),
            ],
            'invited_team_id' => [
                'required',
                Rule::exists('teams', 'id'),
            ],
            'oauth_client_id' => [
                'required',
                Rule::exists('oauth_clients', 'id'),
            ],
        ])->after(function ($validator) use ($invitingTeam, $client) {
            if ($client->team_id !== $invitingTeam->id) {
                $validator->errors()->add('oauth_client_id', __('This client is not owned by the inviting team.'));
            }
        })->validate();
    }
}
