<?php

namespace App\Http\Controllers\Gaia;

use App\Http\Controllers\AccessTokenController;
use App\Http\Controllers\Concerns\ExtractsTokenId;
use App\Http\Controllers\Controller;
use App\Models\ChromeosDevice;
use App\Models\ChromeosDeviceToken;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GAIA token endpoint (`POST oauth2/v4/token`) wrapper.
 *
 * Two jobs on top of the shared Passport token controller:
 *
 * 1. **redirect_uri default** — openFyde's Chromium redeems the sign-in
 *    `oauth_code` WITHOUT a `redirect_uri`, but the minted code embeds one
 *    (config('gaia.redirect_uri')), so league's AuthCodeGrant rejects it
 *    (invalid_request). We inject the configured redirect_uri when absent so the
 *    presented and embedded values match.
 *
 * 2. **Token capture** — record a hashed reference (sha256 + jti) of the issued
 *    token, correlated back to the signing device via the auth code, so an admin
 *    can audit and later revoke it. Capture is side-effect-only and fail-open: a
 *    capture error must never break token issuance.
 */
class TokenController extends Controller
{
    use ExtractsTokenId;

    public function __construct(private AccessTokenController $accessTokenController) {}

    public function issueToken(ServerRequestInterface $psrRequest)
    {
        $body = (array) $psrRequest->getParsedBody();
        $grantType = $body['grant_type'] ?? '';

        if ($grantType === 'authorization_code' && empty($body['redirect_uri'])) {
            $body['redirect_uri'] = config('gaia.redirect_uri');
            $psrRequest = $psrRequest->withParsedBody($body);
        }

        // GAIA impedance mismatch: Chromium refreshes the OAuthLogin "uber" token
        // for per-service scopes — including ones we recognize but did NOT grant at
        // sign-in (e.g. chromeosdevicemanagement for an owner device). Standard
        // OAuth2 refresh forbids widening scope (league throws invalid_scope and
        // 400s the whole request), which aborts OOBE. Clamp the requested scope to
        // the granted set; if nothing requested was granted, drop it so league
        // reuses the original grant. Either way the refresh succeeds.
        if ($grantType === 'refresh_token' && ! empty($body['scope'])) {
            $requested = preg_split('/\s+/', trim((string) $body['scope'])) ?: [];
            $allowed = array_values(array_intersect($requested, (array) config('gaia.scopes', [])));

            if ($allowed !== $requested) {
                Log::channel('gaia')->info('refresh scope clamped to granted set', [
                    'requested' => $requested,
                    'granted' => $allowed,
                ]);
            }

            if ($allowed) {
                $body['scope'] = implode(' ', $allowed);
            } else {
                unset($body['scope']);
            }
            $psrRequest = $psrRequest->withParsedBody($body);
        }

        Log::channel('gaia')->debug('oauth2/v4/token', [
            'grant_type' => $grantType,
            'params' => Arr::except($body, ['client_secret']),
        ]);

        $response = $this->accessTokenController->issueToken($psrRequest);

        try {
            $this->captureTokens($response, $body, $grantType);
        } catch (\Throwable $e) {
            Log::channel('gaia')->error('token capture failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    /**
     * Record hashed references to the issued tokens. For the authorization_code
     * grant we correlate to the device via sha256(code) == device.last_code_hash.
     * Refresh-grant rotations carry no code, so those rows are recorded with a
     * null device (still auditable + revocable by jti).
     */
    private function captureTokens($response, array $body, string $grantType): void
    {
        if (! method_exists($response, 'getStatusCode') || $response->getStatusCode() !== 200) {
            return;
        }

        $data = json_decode($response->getContent(), true) ?: [];
        $accessToken = $data['access_token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;

        if (! $accessToken) {
            return;
        }

        $codeHash = null;
        $deviceId = null;

        if ($grantType === 'authorization_code' && ! empty($body['code'])) {
            $codeHash = hash('sha256', (string) $body['code']);
            $deviceId = ChromeosDevice::where('last_code_hash', $codeHash)->value('id');
        }

        ChromeosDeviceToken::create([
            'chromeos_device_id' => $deviceId,
            'jti' => $this->extractTokenId($accessToken),
            'token_hash' => hash('sha256', $accessToken),
            'type' => 'access',
            'code_hash' => $codeHash,
            'issued_at' => now(),
        ]);

        if ($refreshToken) {
            ChromeosDeviceToken::create([
                'chromeos_device_id' => $deviceId,
                'jti' => null,
                'token_hash' => hash('sha256', $refreshToken),
                'type' => 'refresh',
                'code_hash' => $codeHash,
                'issued_at' => now(),
            ]);
        }
    }
}
