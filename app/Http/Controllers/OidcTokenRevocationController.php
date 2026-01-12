<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

class OidcTokenRevocationController extends Controller
{
    private Sha256 $signer;

    private string $publicKey;

    public function __construct()
    {
        $this->signer = new Sha256();
        $publicKey = config('passport.public_key');
        if (is_string($publicKey) && file_exists($publicKey)) {
            $publicKey = file_get_contents($publicKey);
        }

        if (empty($publicKey)) {
            abort(500, 'Invalid public key, please check your configuration.');
        }
        $this->publicKey = $publicKey;
    }

    /**
     * RFC 7009 token revocation (POST /oauth/revoke).
     * Accepts access_token, refresh_token, or id_token (JWT) and responds 200 on success or unknown token.
     */
    public function __invoke(Request $request)
    {
        $token = $request->input('token');
        $tokenTypeHint = $request->input('token_type_hint');

        if (! $token) {
            return response()->json(['error' => 'Missing token'], 400);
        }

        $client = $this->authenticateClient($request);
        if (! $client) {
            return response()->json(['error' => 'invalid_client'], 401);
        }

        $revoked = false;

        if ($tokenTypeHint === 'refresh_token') {
            $revoked = $this->revokeRefreshToken($token, $client->id);
        } elseif ($tokenTypeHint === 'access_token' || $tokenTypeHint === null) {
            $revoked = $this->revokeAccessToken($token, $client->id);
        }

        if (! $revoked) {
            $revoked = $this->tryBlacklistJwt($token);
        }

        return response()->json(['revoked' => (bool) $revoked], 200);
    }

    protected function authenticateClient(Request $request)
    {
        $clientId = null;
        $clientSecret = null;

        if ($request->hasHeader('Authorization')) {
            [$type, $credentials] = explode(' ', $request->header('Authorization'), 2) + [null, null];
            if (strtolower($type) === 'basic' && $credentials) {
                [$clientId, $clientSecret] = array_map('urldecode', explode(':', base64_decode($credentials), 2));
            }
        }

        $clientId = $clientId ?? $request->input('client_id');
        $clientSecret = $clientSecret ?? $request->input('client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $client = \Laravel\Passport\Client::find($clientId);
        if (! $client || ! hash_equals($client->secret ?? '', $clientSecret)) {
            return null;
        }

        return $client;
    }

    protected function revokeAccessToken(string $tokenId, int|string $clientId): bool
    {
        $record = \Laravel\Passport\Token::find($tokenId);

        if (! $record) {
            $jwt = $this->parseJwt($tokenId);
            if ($jwt) {
                $record = \Laravel\Passport\Token::find($jwt->claims()->get('jti'));
            }
        }

        if (! $record || (string) $record->client_id !== (string) $clientId) {
            return false;
        }

        $record->revoked = true;
        $record->save();

        DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $record->id)
            ->update(['revoked' => true]);

        return true;
    }

    protected function revokeRefreshToken(string $tokenId, int|string $clientId): bool
    {
        $refresh = DB::table('oauth_refresh_tokens')->where('id', $tokenId)->first();
        if (! $refresh) {
            return false;
        }

        $accessToken = $refresh->access_token_id
            ? \Laravel\Passport\Token::find($refresh->access_token_id)
            : null;

        if ($accessToken && (string) $accessToken->client_id !== (string) $clientId) {
            return false;
        }

        $updated = DB::table('oauth_refresh_tokens')
            ->where('id', $tokenId)
            ->update(['revoked' => true]);

        return $updated > 0;
    }

    protected function tryBlacklistJwt(string $token): bool
    {
        if (empty($this->publicKey)) {
            return false;
        }

        try {
            $jwt = $this->parseJwt($token);
            if (! $jwt) {
                return false;
            }
            $jti = $jwt->claims()->get('jti');
            Cache::put('oidc_token_blacklist:'.$jti, true, now()->addDay());

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function parseJwt(string $token): ?\Lcobucci\JWT\Token
    {
        $parser = new Parser(new JoseEncoder);
        $jwt = $parser->parse($token);
        $validator = new Validator;
        $validator->assert(
            $jwt,
            new LooseValidAt(new SystemClock(new \DateTimeZone('UTC'))),
            new SignedWith(
                $this->signer,
                InMemory::plainText($this->publicKey)
            )
        );

        return $jwt;
    }
}
