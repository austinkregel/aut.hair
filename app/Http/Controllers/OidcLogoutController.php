<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class OidcLogoutController extends Controller
{
    /**
     * Handle OIDC end session (logout) requests.
     * Supports GET and POST as per OIDC spec.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        // OIDC params
        $idTokenHint = $request->input('id_token_hint');
        $postLogoutRedirectUri = $request->input('post_logout_redirect_uri');
        $state = $request->input('state');

        // Log out the user (Laravel session)
        Auth::logout();
        Session::flush();

        // If id_token_hint is provided, attempt to revoke/blacklist the token (for production)
        if ($idTokenHint) {
            $this->revokeIdToken($idTokenHint);
        }

        // Validate post_logout_redirect_uri if provided (must be registered/allowed for the client)
        if ($postLogoutRedirectUri && $this->isValidRedirect($postLogoutRedirectUri, $idTokenHint)) {
            $redirectUrl = $postLogoutRedirectUri;
            // OIDC spec: If state is provided, append it
            if ($state) {
                $redirectUrl .= (parse_url($redirectUrl, PHP_URL_QUERY) ? '&' : '?').'state='.urlencode($state);
            }

            // Always return a redirect response (302) for both GET and POST
            return new \Illuminate\Http\RedirectResponse($redirectUrl, 302, ['Location' => $redirectUrl]);
        }

        // Default: Show a simple logged out message
        return response()->view('oidc.loggedout', ['redirect' => $postLogoutRedirectUri, 'state' => $state]);
    }

    /**
     * Validate the post_logout_redirect_uri (must be registered/allowed for the client).
     * In production, this should check the client_id from the id_token_hint (if present)
     * and ensure the redirect URI is registered for that client.
     */
    protected function isValidRedirect($uri, $idTokenHint = null)
    {
        // In production, decode the id_token_hint to get the client_id (aud claim)
        // and check the registered post_logout_redirect_uris for that client.
        if (! $idTokenHint) {
            return false;
        }

        $clientId = $this->extractClientIdFromIdToken($idTokenHint);
        if (! $clientId) {
            return false;
        }

        // Lookup the client in the database (Passport clients table)
        $client = \Laravel\Passport\Client::where('id', $clientId)->first();

        if (! $client) {
            return false;
        }

        // Assume you have a column or config for allowed post_logout_redirect_uris (comma-separated)
        $allowedUris = [];
        if (! empty($client->post_logout_redirect_uris)) {
            $allowedUris = array_map('trim', explode(',', $client->post_logout_redirect_uris));
        } elseif (isset($client->redirect)) {
            // Fallback: allow the main redirect URI
            $allowedUris = [trim($client->redirect)];
        }

        return in_array($uri, $allowedUris, true);
    }

    /**
     * Revoke or blacklist the ID token (JWT) by storing its jti or hash in a blacklist table.
     */
    protected function revokeIdToken($jwt)
    {
        $payload = $this->decodeJwtPayload($jwt);
        if (! $payload) {
            return;
        }
        $jti = $payload['jti'] ?? null;
        if (! $jti) {
            // Optionally, use a hash of the JWT if no jti is present
            $jti = hash('sha256', $jwt);
        }
        cache()->put('oidc_token_blacklist:'.$jti, now());
    }

    /**
     * Decode a JWT and return the payload as an array.
     */
    protected function decodeJwtPayload($jwt)
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Extract the client_id (aud claim) from the id_token_hint JWT.
     */
    protected function extractClientIdFromIdToken($jwt)
    {
        $payload = $this->decodeJwtPayload($jwt);
        if (! $payload) {
            return null;
        }
        // aud can be a string or array
        if (isset($payload['aud'])) {
            return is_array($payload['aud']) ? $payload['aud'][0] : $payload['aud'];
        }

        return null;
    }
}
