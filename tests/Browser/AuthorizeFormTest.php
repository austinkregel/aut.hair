<?php

namespace Tests\Browser;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Passport\ClientRepository;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

class AuthorizeFormTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);
    }

    public function test_authorize_form_carries_nonce_and_pkce_fields_and_submits()
    {
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 0, 0, 0, 'UTC'));

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $client = app(ClientRepository::class)->create(
            $user->id,
            'Dusk Auth Code',
            'http://laravel.test/callback'
        );

        $codeVerifier = str_repeat('z', 64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $nonce = 'dusk-nonce-123';
        $state = 'dusk-state-abc';

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'nonce' => $nonce,
            'max_age' => 300,
            'prompt' => 'consent',
            'claims' => '{"id_token":{"email_verified":null}}',
        ]);

        $this->browse(function (Browser $browser) use ($user, $query, $state, $codeChallenge, $nonce) {
            $browser->loginAs($user)
                ->visit('/oauth/authorize?' . $query)
                ->assertInputValue('state', $state)
                ->assertInputValue('response_type', 'code')
                ->assertInputValue('scope', 'openid profile email')
                ->assertInputValue('code_challenge', $codeChallenge)
                ->assertInputValue('code_challenge_method', 'S256')
                ->assertInputValue('nonce', $nonce)
                ->press('Authorize')
                ->waitForLocation('http://laravel.test/callback', 5)
                ->assertQueryStringHas('state', $state)
                ->assertQueryStringHas('code');
        });

        Carbon::setTestNow(null);
    }
}
