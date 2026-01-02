<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Laravel\Passport\Client;
use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\Parser;
use Psr\Http\Message\ServerRequestInterface;

class MachineTokenController extends Controller
{
    public function __construct(
        private readonly AccessTokenController $accessTokenController,
    ) {
    }

    /**
     * List the current user's client_credentials clients.
     */
    public function clients(Request $request): JsonResponse
    {
        $user = $request->user();

        $clients = Client::query()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->get()
            ->filter(fn (Client $client) => $client->hasGrantType('client_credentials') && $client->confidential())
            ->values()
            ->map(fn (Client $client) => [
                'id' => (string) $client->id,
                'name' => $client->name,
                'grant_types' => $client->grant_types ?? null,
                'scopes' => $client->scopes ?? null,
                'redirect' => $client->redirect,
                'confidential' => $client->confidential(),
                'created_at' => $client->created_at?->toISOString(),
                'updated_at' => $client->updated_at?->toISOString(),
            ]);

        return response()->json(['clients' => $clients]);
    }

    /**
     * Generate a new access token for a client_credentials client owned by the current user.
     * The client secret is never sent to the browser.
     */
    public function generate(Request $request, ServerRequestInterface $psrRequest): JsonResponse
    {
        $allowedScopes = array_keys(config('openid.passport.tokens_can', []));

        $validated = $request->validate([
            'client_id' => ['required'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string', Rule::in($allowedScopes)],
        ]);

        // Ensure OIDC response logic sees the real grant type for this request.
        // Otherwise it may attempt to build an ID token (and look up a user) even for client_credentials.
        $request->merge(['grant_type' => 'client_credentials']);

        $client = Client::query()
            ->where('id', $validated['client_id'])
            ->where('user_id', $request->user()->id)
            ->where('revoked', false)
            ->first();

        if (! $client) {
            return response()->json(['error' => 'invalid_client'], 404);
        }

        if (! $client->confidential() || ! $client->hasGrantType('client_credentials')) {
            return response()->json(['error' => 'invalid_client'], 422);
        }

        $requestedScopes = array_values(array_unique($validated['scopes'] ?? []));
        foreach ($requestedScopes as $scope) {
            if (! $client->hasScope($scope)) {
                return response()->json(['error' => 'invalid_scope'], 422);
            }
        }

        $body = (array) $psrRequest->getParsedBody();
        $body['grant_type'] = 'client_credentials';
        $body['client_id'] = (string) $client->id;
        $body['client_secret'] = (string) $client->secret;
        $body['scope'] = implode(' ', $requestedScopes);

        $machineRequest = $psrRequest->withParsedBody($body);
        try {
            $tokenResponse = $this->accessTokenController->issueToken($machineRequest);
        } catch (\LogicException $e) {
            // Most commonly indicates Passport keys are missing, unreadable, or invalid PEM.
            return response()->json([
                'error' => 'server_error',
                'error_description' => 'Unable to issue access token (invalid Passport keys). Run: php artisan passport:keys',
            ], 500);
        }

        $data = $tokenResponse->getData(true);
        $accessToken = is_array($data) ? ($data['access_token'] ?? null) : null;

        return new JsonResponse(
            array_merge(is_array($data) ? $data : [], [
                'token_id' => $accessToken ? $this->extractTokenId($accessToken) : null,
            ]),
            $tokenResponse->getStatusCode(),
            $tokenResponse->headers->all(),
        );
    }

    /**
     * List access tokens for a given client_credentials client.
     */
    public function tokens(Request $request, string $clientId): JsonResponse
    {
        $client = Client::query()
            ->where('id', $clientId)
            ->where('user_id', $request->user()->id)
            ->where('revoked', false)
            ->first();

        if (! $client) {
            return response()->json(['error' => 'invalid_client'], 404);
        }

        if (! $client->hasGrantType('client_credentials')) {
            return response()->json(['error' => 'invalid_client'], 422);
        }

        $tokens = Token::query()
            ->where('client_id', $client->id)
            ->whereNull('user_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Token $token) => [
                'id' => (string) $token->id,
                'scopes' => is_array($token->scopes) ? $token->scopes : [],
                'revoked' => (bool) $token->revoked,
                'created_at' => $token->created_at?->toISOString(),
                'last_used_at' => $token->last_used_at?->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
                'expired' => $token->expires_at ? $token->expires_at->isPast() : false,
            ]);

        return response()->json([
            'client' => [
                'id' => (string) $client->id,
                'name' => $client->name,
            ],
            'tokens' => $tokens,
        ]);
    }

    /**
     * Revoke an access token belonging to a client owned by the current user.
     */
    public function revoke(Request $request, string $tokenId): JsonResponse
    {
        /** @var Token|null $token */
        $token = Token::find($tokenId);
        if (! $token || $token->user_id !== null) {
            return response()->json(['error' => 'invalid_token'], 404);
        }

        /** @var Client|null $client */
        $client = Client::find($token->client_id);
        if (! $client || (string) $client->user_id !== (string) $request->user()->id) {
            return response()->json(['error' => 'invalid_client'], 404);
        }

        $token->revoke();
        Cache::put('oidc_token_blacklist:' . $token->id, true, now()->addDay());

        return response()->json(['revoked' => true]);
    }

    private function extractTokenId(string $jwtString): ?string
    {
        try {
            $parser = new Parser(new JoseEncoder());
            $jwt = $parser->parse($jwtString);
            if (! $jwt instanceof Plain) {
                return null;
            }

            $jti = $jwt->claims()->get('jti');
            return is_string($jti) && $jti !== '' ? $jti : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

