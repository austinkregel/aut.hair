<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Client;

class ClientListController extends Controller
{
    public function byTeam(Request $request, $teamId)
    {
        Gate::authorize('viewAny', [Client::class, null]);

        return Client::query()
            ->where('team_id', $teamId)
            ->where('revoked', false)
            ->orderByDesc('id')
            ->get(['id', 'name']);
    }
}
