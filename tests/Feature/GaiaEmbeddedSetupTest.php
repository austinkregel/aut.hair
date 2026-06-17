<?php

namespace Tests\Feature;

use App\Gaia\GaiaIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAIA sign-in webview page (embedded/setup/v2/chromeos) — slice 2a.
 *
 * Covers the byte-exact completion contract the OOBE webview host reads:
 * the `google-accounts-signin` header, the `oauth_code` cookie, and the
 * postMessage wiring. Session-authenticated (web guard), since the device
 * authenticates against aut.hair's normal login before reaching this page.
 */
class GaiaEmbeddedSetupTest extends TestCase
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

    public function test_unauthenticated_device_gets_saml_bootstrap_page(): void
    {
        // No session => the controller returns a 200 HTML page (not a 302) that
        // carries google-accounts-saml: start and JS-redirects to /login.
        // A server-side 302 would have no loadcommit of its own, so isSamlPage_
        // would be false when /login's document_start fires and the
        // PasswordInputScraper would never initialize — the device powerwashes.
        $response = $this->get(route('gaia.embedded-setup'));

        $response->assertStatus(200);
        $this->assertSame('start', $response->headers->get('google-accounts-saml'));
        $response->assertSee('window.location.replace', false);
        // Controller stores url.intended so isGaiaFlow() fires on /login.
        $response->assertSessionHas('url.intended');
    }

    public function test_authenticated_page_emits_the_signin_contract(): void
    {
        $user = User::factory()->create([
            'email' => 'User@AUT.hair',          // mixed case on purpose
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('gaia.embedded-setup'));

        $response->assertStatus(200);

        // google-accounts-signin header: email + obfuscatedid + sessionindex.
        // The host lowercases the whole value, so we must emit it lowercase.
        $header = $response->headers->get('google-accounts-signin');
        $this->assertNotNull($header, 'google-accounts-signin header must be present');
        $this->assertStringContainsString('email="user@aut.hair"', $header);
        // Opaque gaia id (coupled with userinfo `id`), not the sequential PK.
        $this->assertStringContainsString('obfuscatedid="'.GaiaIdentity::for($user).'"', $header);
        $this->assertStringNotContainsString('obfuscatedid="'.$user->id.'"', $header);
        $this->assertStringContainsString('sessionindex=0', $header);

        // oauth_code cookie (the auth code the browser exchanges later).
        $response->assertCookie('oauth_code');

        // The page wires the handshake -> userInfo -> closeView completion.
        $response->assertSee('handshake', false);
        $response->assertSee('userInfo', false);
        $response->assertSee('closeView', false);
    }

    public function test_misconfigured_client_renders_error_page_not_500(): void
    {
        // A provisioning mistake (here: gaia.client_id points at a client that
        // doesn't exist) must surface as a clean error page, not a 500 mid-OOBE.
        config(['gaia.client_id' => 'does-not-exist']);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('gaia.embedded-setup'));

        $response->assertStatus(200);
        $response->assertSee('Sign-in is temporarily unavailable', false);
        // It must NOT pretend to complete: no completion wiring, no auth cookie.
        $response->assertDontSee('closeView', false);
        $response->assertCookieMissing('oauth_code');
    }

    public function test_oauth_code_cookie_is_not_empty(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('gaia.embedded-setup'));

        $value = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'oauth_code')?->getValue();

        $this->assertNotEmpty($value, 'oauth_code cookie must carry a non-empty value');
    }
}
