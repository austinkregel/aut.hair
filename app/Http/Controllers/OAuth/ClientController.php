<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class ClientController extends Controller
{
    public function __construct(protected ClientRepository $clients) {}

    public function forUser(Request $request)
    {
        $team = $this->currentTeam($request);
        Gate::authorize('viewAny', [Client::class, $team]);

        return Client::query()
            ->where('team_id', $team?->id)
            ->where('revoked', false)
            ->orderByDesc('id')
            ->get();
    }

    public function store(Request $request)
    {
        $team = $this->currentTeam($request);
        Gate::authorize('create', [Client::class, $team]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'redirect' => ['required', 'string'],
            'confidential' => ['sometimes', 'boolean'],
            'grant_types' => ['sometimes', 'array'],
            'grant_types.*' => ['string'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string'],
        ]);

        $client = $this->clients->create(
            null,
            $validated['name'],
            $validated['redirect'],
            null,
            false,
            false,
            $request->boolean('confidential', true)
        );

        $client->team_id = $team?->id;
        $client->user_id = null;
        $client->save();

        $client->forceFill([
            'grant_types' => $request->input('grant_types', []),
            'scopes' => $request->input('scopes', []),
        ])->save();

        return $client->fresh();
    }

    public function update(Request $request, $clientId)
    {
        $team = $this->currentTeam($request);
        $client = Client::findOrFail($clientId);

        Gate::authorize('update', [$client, $team]);

        $isCurrentlyConfidential = ! empty($client->secret);
        $isConfidential = $request->has('confidential')
            ? $request->boolean('confidential', true)
            : $isCurrentlyConfidential;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'redirect' => ['required', 'string'],
            'confidential' => ['sometimes', 'boolean'],
            'grant_types' => ['sometimes', 'array'],
            'grant_types.*' => ['string'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string'],
        ]);

        $client->forceFill([
            'name' => $validated['name'],
            'redirect' => $validated['redirect'],
            // Passport models confidential clients by having a secret; public clients have a null secret.
            'secret' => $isConfidential ? ($client->secret ?: Str::random(40)) : null,
            'personal_access_client' => false,
            'password_client' => false,
            'team_id' => $team?->id,
            'grant_types' => $request->input('grant_types', $client->getAttribute('grant_types') ?? []),
            'scopes' => $request->input('scopes', $client->getAttribute('scopes') ?? []),
        ])->save();

        return $client->fresh();
    }

    public function destroy(Request $request, $clientId)
    {
        $team = $this->currentTeam($request);
        $client = Client::findOrFail($clientId);

        Gate::authorize('delete', [$client, $team]);

        $client->revoked = true;
        $client->save();

        return response()->noContent();
    }

    protected function currentTeam(Request $request)
    {
        $user = $request->user();

        $requestedTeamId = $request->input('team_id');

        if ($requestedTeamId && $user) {
            $team = $user->allTeams()->firstWhere('id', (int) $requestedTeamId);
            if ($team) {
                return $team;
            }
        }

        return $user?->currentTeam ?? $user?->allTeams()->first();
    }
}

