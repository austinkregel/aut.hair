<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SAML mode for the openFyde/ChromeOS GAIA OOBE flow: ChromeOS only captures the
 * typed password (the cryptohome factor that prevents the silent powerwash) when
 * the login page emits `google-accounts-saml: start`. The PasswordInputScraper
 * injected by ChromeOS then auto-scrapes the password field and stores it on the
 * handler side, where it persists across the /login → /embedded/setup navigation.
 * That must happen ONLY inside the GAIA flow, never for normal logins.
 */
class GaiaSamlModeTest extends TestCase
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

    public function test_a_normal_login_is_not_put_into_saml_mode(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $this->assertNull($response->headers->get('google-accounts-saml'));
    }

    public function test_embedded_setup_marks_the_flow_and_login_enters_saml_mode(): void
    {
        // An unauthenticated device hitting the GAIA entry point must receive an
        // HTML page (not a 302) that carries google-accounts-saml: start and uses a
        // JavaScript redirect to /login. A server-side 302 would skip the loadcommit
        // for /embedded/setup; without that loadcommit, isSamlPage_ is false when
        // /login's document_start fires and the PasswordInputScraper never inits.
        $setup = $this->get(route('gaia.embedded-setup'));

        $setup->assertStatus(200);
        $this->assertSame('start', $setup->headers->get('google-accounts-saml'));
        // JS redirect is required — a meta-refresh or server 302 won't produce the
        // intermediate loadcommit that sets isSamlPage_ = true.
        $setup->assertSee('window.location.replace', false);
        // Auth middleware still ran and stored url.intended before we intercepted.
        $setup->assertSessionHas('url.intended');

        // The login page (same session) also emits the SAML header (belt-and-
        // suspenders: keeps pendingIsSamlPage_ = true at /login's loadcommit).
        $login = $this->get('/login');

        $login->assertStatus(200);
        $this->assertSame('start', $login->headers->get('google-accounts-saml'));
        $login->assertDontSee('gaia_saml_api', false);
    }

    public function test_the_completion_page_is_not_in_saml_mode(): void
    {
        // gaia.client_id is unset in tests, so the controller takes the placeholder
        // path (no Passport keys/client needed) and returns the page.
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('gaia.embedded-setup'));

        $response->assertStatus(200);
        // The completion page stays a plain GAIA response (SAML belongs on /login).
        $this->assertNull($response->headers->get('google-accounts-saml'));
    }
}
