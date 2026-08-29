<?php

namespace App\Http\Controllers;

use App\Services\OAuth\TeamAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Client as PassportClient;

/**
 * Per-client entitlement and permissions for the bearer of an access token.
 *
 * `CheckOAuthTeamAccess` already refuses `/oauth/authorize` for a user whose
 * teams are not entitled to the client, so a relying party knows an issued
 * token implies entitlement *at the moment it was issued*. It has no way to
 * learn anything beyond that: the id_token carries only name/email
 * (`UserEntity::getClaims`) and `/api/userinfo` adds only picture and
 * email_verified. A relying party that wants to gate its own privileged
 * actions — a remote shell, a fleet-wide config push — therefore has nothing
 * to gate on, and re-checking entitlement after login is impossible.
 *
 * This endpoint closes both gaps. The access token identifies the user AND the
 * client, so no parameters are accepted: a caller can only ever ask about
 * itself, and cannot probe another client's entitlements by guessing ids.
 *
 * Answers are deliberately distinguishable, because a relying party must be
 * able to tell "this user is not entitled" from "aut.hair did not answer" and
 * fail closed differently for each:
 *
 *   200  entitled; `permissions` may be empty if the entitling team carries no role
 *   401  no/unparseable/unknown token
 *   403  `insufficient_scope` (no openid) or `not_entitled` (no team grants access)
 */
class ClientPermissionsController extends Controller
{
    public function __construct(protected TeamAuthorizationService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->tokenCan('openid')) {
            return response()->json(['error' => 'insufficient_scope'], 403);
        }

        $client = $this->clientFromRequest($request);

        if (! $client) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $teams = $this->service->getUserTeamsWithAccess($user, $client);

        // Entitlement is re-evaluated live here, not read off the token. A team
        // whose access was revoked after the token was issued lands in this
        // branch, which is the point: it is what lets a relying party notice a
        // revocation instead of trusting a login decision indefinitely.
        if ($teams->isEmpty()) {
            return response()->json([
                'error' => 'not_entitled',
                'sub' => (string) $user->id,
                'client_id' => (string) $client->id,
            ], 403);
        }

        return response()->json([
            'sub' => (string) $user->id,
            'client_id' => (string) $client->id,
            // Only the teams entitling this user to THIS client — never the
            // user's full membership, which would leak unrelated teams to the
            // relying party. Same rule ForwardAuthController::groupsFor applies.
            'teams' => $teams->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'permissions' => $this->service->getUserPermissionsForClient($user, $client),
        ]);
    }

    /**
     * Resolve the Passport client the presented access token was issued to.
     *
     * Read off the token the `auth:api` guard already resolved
     * (`TokenGuard` calls `withAccessToken`), not by re-parsing the bearer JWT
     * as MachineInfoController does — that endpoint has no guard and must parse
     * for itself. Re-parsing behind a guard is redundant work that can disagree
     * with the guard's own answer.
     *
     * Taken from the token rather than a request parameter so a caller can only
     * ever ask about the client it authenticated as.
     */
    protected function clientFromRequest(Request $request): ?PassportClient
    {
        $clientId = $request->user()?->token()?->client_id;

        if (! $clientId) {
            return null;
        }

        return PassportClient::find($clientId);
    }
}
