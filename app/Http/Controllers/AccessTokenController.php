<?php

namespace App\Http\Controllers;

use App\Repositories\KeyRepositoryContract;
use Illuminate\Http\JsonResponse;
use Laravel\Passport\Http\Controllers\AccessTokenController as PassportAccessTokenController;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

class AccessTokenController extends PassportAccessTokenController
{
    protected KeyRepositoryContract $keyRepository;

    public function __construct(
        AuthorizationServer $server,
        TokenRepository $tokens,
        KeyRepositoryContract $keyRepository
    ) {
        parent::__construct($server, $tokens);
        $this->keyRepository = $keyRepository;
    }

    public function issueToken(ServerRequestInterface $request)
    {
        $payload = $request->getParsedBody() ?? [];
        $grantType = $payload['grant_type'] ?? '';

        $teamIdForFlow = null;
        if ($grantType === 'authorization_code') {
            $code = $payload['code'] ?? '';

            // code is always required for authorization_code grant
            if ($code === '') {
                return new SymfonyJsonResponse([
                    'error' => 'invalid_request',
                    'error_description' => 'code is required for authorization_code.',
                ], 400);
            }

            // client_id may come from:
            // 1. The request body (client_secret_post)
            // 2. The Authorization header (client_secret_basic)
            // 3. The auth code record (inferred)
            $clientId = $payload['client_id'] ?? $this->extractClientIdFromBasicAuth($request) ?? '';

            if (! $this->enrichTeamContext($code, $clientId, $teamIdForFlow)) {
                return new SymfonyJsonResponse([
                    'error' => 'invalid_request',
                    'error_description' => 'team_id is required for this authorization code.',
                ], 422);
            }
        }

        return $this->withErrorHandling(function () use ($request, $teamIdForFlow) {
            $psrResponse = $this->server->respondToAccessTokenRequest($request, new Psr7Response);
            $laravelResponse = $this->convertResponse($psrResponse);
            $data = json_decode($laravelResponse->getContent(), true);

            $this->stampTeamOnAccessToken($data['access_token'] ?? null, $teamIdForFlow);

            return new JsonResponse($data, $laravelResponse->getStatusCode(), $laravelResponse->headers->all());
        });
    }

    /**
     * Extract client_id from HTTP Basic Authorization header (client_secret_basic auth method).
     */
    private function extractClientIdFromBasicAuth(ServerRequestInterface $request): ?string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader) || ! str_starts_with($authHeader, 'Basic ')) {
            return null;
        }

        $credentials = base64_decode(substr($authHeader, 6), true);
        if ($credentials === false) {
            return null;
        }

        $parts = explode(':', $credentials, 2);
        if (count($parts) < 1 || $parts[0] === '') {
            return null;
        }

        return urldecode($parts[0]);
    }

    private function enrichTeamContext(?string $code, ?string &$clientId, ?int &$teamIdForFlow): bool
    {
        $authCode = $code ? DB::table('oauth_auth_codes')->where('id', $code)->first() : null;
        if ($authCode) {
            $teamIdForFlow = $authCode->team_id;
            $clientId = $authCode->client_id ?: $clientId;
        }

        if (! $teamIdForFlow && $clientId) {
            $teamIdForFlow = DB::table('oauth_clients')->where('id', $clientId)->value('team_id');
        }

        return (bool) $teamIdForFlow;
    }

    private function stampTeamOnAccessToken(?string $accessToken, ?int $teamId): void
    {
        if (empty($accessToken) || ! $teamId) {
            return;
        }

        $jti = $this->extractJtiFromJwt($accessToken);
        if (! $jti) {
            return;
        }

        DB::table('oauth_access_tokens')
            ->where('id', $jti)
            ->update(['team_id' => $teamId]);
    }

    private function extractJtiFromJwt(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        return $payload['jti'] ?? null;
    }

    private function base64UrlDecode(string $input): string|false
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }
}
