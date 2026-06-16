<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/**
 * End-to-end GAIA sign-in (server side) — slice 2b-ii.
 *
 * Proves the whole chain CI can exercise: the sign-in page mints a real Passport
 * authorization code (cookie) for the confidential ChromeOS client, and the GAIA
 * token endpoint (oauth2/v4/token) redeems it for tokens. The only piece left
 * for on-device validation is the webview postMessage handshake itself.
 */
class GaiaSignInFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);
    }

    public function test_sign_in_page_mints_a_code_that_the_token_endpoint_redeems(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'owner@aut.hair',
            'email_verified_at' => now(),
        ]);

        // Provision the confidential ChromeOS client the way cros:setup does,
        // but inline so we keep the plain secret for the exchange.
        $clients = app(ClientRepository::class);
        $client = $clients->create(null, 'openFyde ChromeOS', config('gaia.redirect_uri'), null, false, false, true);
        $client->team_id = $user->currentTeam->id;
        $client->user_id = null;
        $client->save();
        $client->forceFill([
            'grant_types' => ['authorization_code', 'refresh_token'],
            'scopes' => config('gaia.scopes'),
        ])->save();
        $plainSecret = $client->plainSecret;

        config(['gaia.client_id' => $client->id]);

        // 1. The webview loads the sign-in page (authenticated session) and gets
        //    a raw (un-encrypted) oauth_code cookie.
        $page = $this->actingAs($user)->get(route('gaia.embedded-setup'));
        $page->assertStatus(200);

        $code = collect($page->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'oauth_code')?->getValue();
        $this->assertNotEmpty($code, 'sign-in page must set a non-empty oauth_code cookie');

        // 2. The device exchanges the code at the GAIA token endpoint.
        $token = $this->post('/oauth2/v4/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $client->id,
            'client_secret' => $plainSecret,
            'redirect_uri' => config('gaia.redirect_uri'),
        ]);

        $token->assertStatus(200);
        $token->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);
    }
}
