<?php

namespace App\Http\Controllers;

use App\Repositories\KeyRepositoryContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Http\Controllers\AccessTokenController as PassportAccessTokenController;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;
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
            $clientId = $payload['client_id'] ?? '';

            if ($code === '' || $clientId === '') {
                return new SymfonyJsonResponse([
                    'error' => 'invalid_request',
                    'error_description' => 'code and client_id are required for authorization_code.',
                ], 422);
            }

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
