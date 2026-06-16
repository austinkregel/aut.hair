<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Jetstream;
use Laravel\Passport\Client;

class InviteTeamToOAuthClient
{
    public function invite($user, Team $invitingTeam, Team $invitedTeam, Client $client, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('inviteTeam', [$client, $invitingTeam]);

        $this->validate($invitingTeam, $invitedTeam, $client, $role);

        // Each invitation is unique per (inviting team, invited team, client).
        // A belongsToMany sync keys only on invited_team_id, so it would overwrite
        // an existing grant when the same team is invited to a second client.
        $keys = [
            'inviting_team_id' => $invitingTeam->id,
            'invited_team_id' => $invitedTeam->id,
            'oauth_client_id' => $client->id,
        ];

        $existing = DB::table('oauth_client_team_invitations')->where($keys)->first();

        if ($existing) {
            DB::table('oauth_client_team_invitations')
                ->where('id', $existing->id)
                ->update(['role' => $role, 'updated_at' => now()]);
        } else {
            DB::table('oauth_client_team_invitations')->insert($keys + [
                'role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function validate(Team $invitingTeam, Team $invitedTeam, Client $client, ?string $role): void
    {
        Validator::make([
            'inviting_team_id' => $invitingTeam->id,
            'invited_team_id' => $invitedTeam->id,
            'oauth_client_id' => $client->id,
            'role' => $role,
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
            'role' => array_filter([
                Jetstream::hasRoles() ? ['nullable', 'string'] : null,
                Jetstream::hasRoles() ? new \Laravel\Jetstream\Rules\Role : null,
            ]),
        ])->after(function ($validator) use ($invitingTeam, $invitedTeam, $client) {
            if ($client->team_id !== $invitingTeam->id) {
                $validator->errors()->add('oauth_client_id', __('This client is not owned by the inviting team.'));
            }

            $alreadyInvited = DB::table('oauth_client_team_invitations')
                ->where('inviting_team_id', $invitingTeam->id)
                ->where('invited_team_id', $invitedTeam->id)
                ->where('oauth_client_id', $client->id)
                ->exists();

            if ($alreadyInvited) {
                $validator->errors()->add('invited_team_id', __('This team is already invited to the client.'));
            }
        })->validate();
    }
}

