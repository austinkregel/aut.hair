<?php

namespace App\Http\Controllers\OAuth;

use App\Actions\Jetstream\InviteTeamToOAuthClient;
use App\Actions\Jetstream\RemoveTeamFromOAuthClient;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Client;

class TeamOAuthClientController extends Controller
{
    public function inviteTeam(
        Request $request,
        Team $team,
        Client $client,
        InviteTeamToOAuthClient $inviter
    ) {
        $data = Validator::make($request->all(), [
            'invited_team_id' => ['required', 'exists:teams,id'],
            'role' => ['nullable', 'string'],
        ])->validate();

        $invitedTeam = Team::findOrFail($data['invited_team_id']);

        $inviter->invite($request->user(), $team, $invitedTeam, $client, $data['role'] ?? null);

        return response()->noContent();
    }

    public function removeTeam(
        Request $request,
        Team $team,
        Client $client,
        Team $invitedTeam,
        RemoveTeamFromOAuthClient $remover
    ) {
        $remover->remove($request->user(), $team, $invitedTeam, $client);

        return response()->noContent();
    }
}

