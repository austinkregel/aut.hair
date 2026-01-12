<?php

namespace App\Http\Controllers\OAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveAuthorizationController;

class ApproveAuthorizationController extends PassportApproveAuthorizationController
{
    public function approve(Request $request)
    {
        $teamId = $request->input('team_id') ?? $request->attributes->get('oauth_team')?->id;
        $clientId = $request->input('client_id');
        $userId = $request->user()?->getAuthIdentifier();

        if (! $teamId || ! $clientId || ! $userId) {
            return response()->json([
                'message' => 'team_id, client_id, and authenticated user are required for authorization.',
            ], 422);
        }

        $response = parent::approve($request);

        $codeFromRedirect = $this->extractCodeFromRedirect($response);
        if ($codeFromRedirect) {
            $this->setTeamOnCode($codeFromRedirect, $teamId);

            return $response;
        }

        $latestCode = DB::table('oauth_auth_codes')
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->orderByDesc('expires_at')
            ->first();

        if ($latestCode) {
            $this->setTeamOnCode($latestCode->id, $teamId);
        }

        return $response;
    }

    private function extractCodeFromRedirect($response): ?string
    {
        if (! $response instanceof \Illuminate\Http\RedirectResponse) {
            return null;
        }

        $location = $response->headers->get('Location');
        if (! $location) {
            return null;
        }

        parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $params);

        return $params['code'] ?? null;
    }

    private function setTeamOnCode(string $code, int $teamId): void
    {
        DB::table('oauth_auth_codes')
            ->where('id', $code)
            ->update(['team_id' => $teamId]);
    }
}
