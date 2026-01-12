<?php

namespace App\Http\Middleware;

use App\Services\OAuth\TeamAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Passport\Client;

class CheckOAuthTeamAccess
{
    public function __construct(protected TeamAuthorizationService $service) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->input('client_id');

        if (! $clientId) {
            abort(422, 'client_id is required.');
        }

        $client = Client::find($clientId);

        if (! $client) {
            abort(404, 'Client not found.');
        }

        $user = $request->user();
        if (! $user) {
            abort(401, 'Authentication required.');
        }

        $accessibleTeams = $this->service->getUserTeamsWithAccess($user, $client);

        if ($accessibleTeams->isEmpty()) {
            abort(403, 'You do not have access to this client.');
        }

        // Determine selected team; if multiple require explicit choice.
        $selectedTeamId = $request->input('team_id');
        if ($accessibleTeams->count() > 1 && ! $selectedTeamId) {
            abort(422, 'team_id is required when you belong to multiple teams for this client.');
        }

        $team = $selectedTeamId
            ? $accessibleTeams->firstWhere('id', $selectedTeamId)
            : $accessibleTeams->first();

        if (! $team) {
            abort(403, 'You do not have access to this client.');
        }

        $request->attributes->set('oauth_team', $team);

        return $next($request);
    }
}

