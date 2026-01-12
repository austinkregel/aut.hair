<?php

namespace Tests\Browser;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Laravel\Dusk\Browser;
use Laravel\Passport\ClientRepository;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Tests\DuskTestCase;

class OAuthApprovalFlowTest extends DuskTestCase
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

    public function test_oauth_approval_flow_with_nonce_includes_nonce_in_id_token(): void
    {
        $now = Carbon::create(2024, 1, 1, 0, 0, 0, 'UTC');
        Carbon::setTestNow($now);

        $user = User::factory()->withPersonalTeam()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $client = app(ClientRepository::class)->create(
            $user->id,
            'Dusk Auth Code Nonce',
            url('/callback')
        );
        $teamId = $user->ownedTeams()->value('id');
        $client->forceFill(['team_id' => $teamId])->save();

        [$codeVerifier, $codeChallenge] = $this->makePkce();
        $nonce = 'dusk-nonce-claims';
        $state = 'state-claims-1';

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'nonce' => $nonce,
            'prompt' => 'consent',
        ]);

        $authCode = '';

        $this->browse(function (Browser $browser) use ($user, $query, &$authCode, $state) {
            $browser->loginAs($user)
                ->visit('/oauth/authorize?' . $query)
                ->press('Authorize')
                ->waitForLocation('/callback', 5)
                ->assertQueryStringHas('state', $state)
                ->assertQueryStringHas('code');

            $currentUrl = $browser->driver->getCurrentURL();
            parse_str(parse_url($currentUrl, PHP_URL_QUERY), $callbackQuery);
            $authCode = isset($callbackQuery['code']) ? (string) $callbackQuery['code'] : '';
        });

        $this->assertNotEmpty($authCode, 'Authorization code should be present in callback');

        $tokenResponse = $this->exchangeToken(
            $authCode,
            $codeVerifier,
            $client,
            $client->redirect
        );

        $this->assertTrue($tokenResponse->ok(), 'Token response should be 200');
        $idToken = $tokenResponse->json('id_token');
        $this->assertNotEmpty($idToken, 'ID token must be returned');

        $jwt = $this->parseIdToken($idToken);
        $claims = $jwt->claims()->all();

        $this->assertArrayHasKey('nonce', $claims, 'Nonce should be present');
        $this->assertSame($nonce, $claims['nonce']);
        $this->assertArrayHasKey('auth_time', $claims);

        $authTime = $claims['auth_time'];
        $this->assertIsInt($authTime, 'auth_time must be an integer timestamp.');

        // In Dusk the app server runs in a separate process, so Carbon::setTestNow() in the test
        // would not affect auth_time. Assert auth_time is close to the current wall clock instead.
        $this->assertGreaterThanOrEqual($testStartedAt->subMinutes(5)->timestamp, $authTime);
        $this->assertLessThanOrEqual(Carbon::now('UTC')->addMinutes(5)->timestamp, $authTime);
    }

    public function test_oauth_approval_flow_without_nonce_does_not_include_nonce(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $client = app(ClientRepository::class)->create(
            $user->id,
            'Dusk Auth Code No Nonce',
            url('/callback')
        );
        $teamId = $user->ownedTeams()->value('id');
        $client->forceFill(['team_id' => $teamId])->save();

        [$codeVerifier, $codeChallenge] = $this->makePkce();
        $state = 'state-no-nonce';

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ]);

        $authCode = '';

        $this->browse(function (Browser $browser) use ($user, $query, &$authCode, $state) {
            $browser->loginAs($user)
                ->visit('/oauth/authorize?' . $query)
                ->press('Authorize')
                ->waitForLocation('/callback', 5)
                ->assertQueryStringHas('state', $state)
                ->assertQueryStringHas('code');

            $currentUrl = $browser->driver->getCurrentURL();
            parse_str(parse_url($currentUrl, PHP_URL_QUERY), $callbackQuery);
            $authCode = isset($callbackQuery['code']) ? (string) $callbackQuery['code'] : '';
        });

        $this->assertNotEmpty($authCode, 'Authorization code should be present in callback');

        $tokenResponse = $this->exchangeToken(
            $authCode,
            $codeVerifier,
            $client,
            $client->redirect
        );

        $this->assertTrue($tokenResponse->ok(), 'Token response should be 200');
        $idToken = $tokenResponse->json('id_token');
        $this->assertNotEmpty($idToken, 'ID token must be returned');

        $claims = $this->parseIdToken($idToken)->claims()->all();
        $this->assertArrayNotHasKey('nonce', $claims, 'Nonce should be absent when not provided');
    }

    public function test_oauth_approval_flow_preserves_state_parameter(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $client = app(ClientRepository::class)->create(
            $user->id,
            'Dusk Auth Code State',
            url('/callback')
        );
        $teamId = $user->ownedTeams()->value('id');
        $client->forceFill(['team_id' => $teamId])->save();

        [$codeVerifier, $codeChallenge] = $this->makePkce();
        $state = 'dusk-state-keep';

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ]);

        $authCode = '';
        $callbackQuery = [];

        $this->browse(function (Browser $browser) use ($user, $query, &$authCode, &$callbackQuery, $state) {
            $browser->loginAs($user)
                ->visit('/oauth/authorize?' . $query)
                ->press('Authorize')
                ->waitForLocation('/callback', 5)
                ->assertQueryStringHas('state', $state)
                ->assertQueryStringHas('code');

            $currentUrl = $browser->driver->getCurrentURL();
            parse_str(parse_url($currentUrl, PHP_URL_QUERY), $callbackQuery);
            $authCode = isset($callbackQuery['code']) ? (string) $callbackQuery['code'] : '';
        });

        $this->assertSame($state, $callbackQuery['state'] ?? null, 'State must be preserved');
        $this->assertNotEmpty($authCode, 'Authorization code should be present in callback');

        $tokenResponse = $this->exchangeToken(
            $authCode,
            $codeVerifier,
            $client,
            $client->redirect
        );

        $this->assertTrue($tokenResponse->ok(), 'Token response should be 200');
    }

    public function test_oauth_approval_flow_with_pkce_validates_code_verifier(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);

        $client = app(ClientRepository::class)->create(
            $user->id,
            'Dusk Auth Code PKCE',
            url('/callback')
        );
        $teamId = $user->ownedTeams()->value('id');
        $client->forceFill(['team_id' => $teamId])->save();

        [$codeVerifier, $codeChallenge] = $this->makePkce();
        $state = 'pkce-state';

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ]);

        $authCode = '';

        $this->browse(function (Browser $browser) use ($user, $query, &$authCode, $state) {
            $browser->loginAs($user)
                ->visit('/oauth/authorize?' . $query)
                ->press('Authorize')
                ->waitForLocation('/callback', 5)
                ->assertQueryStringHas('state', $state)
                ->assertQueryStringHas('code');

            $currentUrl = $browser->driver->getCurrentURL();
            parse_str(parse_url($currentUrl, PHP_URL_QUERY), $callbackQuery);
            $authCode = isset($callbackQuery['code']) ? (string) $callbackQuery['code'] : '';
        });

        $this->assertNotEmpty($authCode, 'Authorization code should be present in callback');

        $invalidResponse = $this->exchangeToken(
            $authCode,
            'invalid-code-verifier',
            $client,
            $client->redirect
        );
        $this->assertFalse($invalidResponse->ok(), 'Token exchange must fail with wrong code_verifier');

        $validResponse = $this->exchangeToken(
            $authCode,
            $codeVerifier,
            $client,
            $client->redirect
        );
        $this->assertTrue($validResponse->ok(), 'Token exchange must succeed with correct code_verifier');
    }

    private function makePkce(): array
    {
        $codeVerifier = str_repeat('v', 64);
        $codeChallenge = rtrim(
            strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'),
            '='
        );

        return [$codeVerifier, $codeChallenge];
    }

    private function exchangeToken(string $code, string $codeVerifier, $client, string $redirectUri)
    {
        return Http::asForm()->post(url('/oauth/token'), [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);
    }

    private function parseIdToken(string $idToken): UnencryptedToken
    {
        return (new Parser(new JoseEncoder()))->parse($idToken);
    }
}

