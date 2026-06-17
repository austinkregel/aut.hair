<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SAML mode for the openFyde/ChromeOS GAIA OOBE flow: ChromeOS only captures the
 * typed password (the cryptohome factor that prevents the silent powerwash) when
 * the login page emits `google-accounts-saml` and runs the gaia_saml_api
 * handshake. That must happen ONLY inside the GAIA flow, never for normal logins.
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
        $response->assertDontSee('gaia_saml_api', false);
    }

    public function test_embedded_setup_marks_the_flow_and_login_enters_saml_mode(): void
    {
        // An unauthenticated device hitting the GAIA entry point is bounced to
        // login; `auth` records the GAIA page as the intended URL.
        $this->get(route('gaia.embedded-setup'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended');

        // The login page (same session) now emits the SAML header and the
        // Credentials Passing API handshake so ChromeOS captures the password.
        $login = $this->get('/login');

        $login->assertStatus(200);
        // Must be exactly "start" — ChromeOS matches the value, not mere presence.
        $this->assertSame('start', $login->headers->get('google-accounts-saml'));
        $login->assertSee('gaia_saml_api', false);
        $login->assertSee('KEY_TYPE_PASSWORD_PLAIN', false);
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
