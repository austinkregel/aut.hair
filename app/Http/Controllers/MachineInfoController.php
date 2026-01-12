<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\Token as PassportToken;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Throwable;

class MachineInfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();
        if (! $accessToken) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $tokenId = $this->extractTokenId($accessToken);
        if (! $tokenId) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        /** @var PassportToken|null $token */
        $token = PassportToken::find($tokenId);
        if (! $token) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $scopes = is_array($token->scopes) ? $token->scopes : [];
        if (! in_array('openid', $scopes, true)) {
            return response()->json(['error' => 'insufficient_scope'], 403);
        }

        /** @var PassportClient|null $client */
        $client = PassportClient::find($token->client_id);
        if (! $client) {
            return response()->json(['error' => 'invalid_client'], 401);
        }

        return response()->json([
            'client_id' => (string) $client->id,
            'name' => $client->name,
            'scopes' => $scopes,
        ]);
    }

    private function extractTokenId(string $jwtString): ?string
    {
        try {
            $parser = new Parser(new JoseEncoder);
            $jwt = $parser->parse($jwtString);

            if (! $jwt instanceof Plain) {
                return null;
            }

            $jti = $jwt->claims()->get('jti');

            return is_string($jti) && $jti !== '' ? $jti : null;
        } catch (Throwable) {
            return null;
        }
    }
}
