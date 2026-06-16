<?php

namespace Tests\Feature;

use App\Actions\Gaia\BlacklistChromeosToken;
use App\Models\ChromeosDevice;
use App\Models\ChromeosDeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Device tracking, audit logging, token capture, and revocation for the
 * openFyde/ChromeOS GAIA sign-in flow.
 */
class GaiaDeviceTrackingTest extends TestCase
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

    /** Provision the confidential ChromeOS client the way cros:setup does. */
    private function provisionClient(User $user): array
    {
        $client = app(ClientRepository::class)
            ->create(null, 'openFyde ChromeOS', config('gaia.redirect_uri'), null, false, false, true);
        $client->team_id = $user->currentTeam->id;
        $client->user_id = null;
        $client->save();
        $client->forceFill([
            'grant_types' => ['authorization_code', 'refresh_token'],
            'scopes' => config('gaia.scopes'),
        ])->save();

        config(['gaia.client_id' => $client->id]);

        return [$client, $client->plainSecret];
    }

    public function test_client_id_mismatch_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        config(['gaia.client_id' => '22']);

        $this->actingAs($user)
            ->get(route('gaia.embedded-setup', ['client_id' => '999']))
            ->assertStatus(403);
    }

    public function test_matching_or_absent_client_id_is_allowed(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        [$client] = $this->provisionClient($user);

        $this->actingAs($user)
            ->get(route('gaia.embedded-setup', ['client_id' => $client->id]))
            ->assertStatus(200);

        $this->actingAs($user)
            ->get(route('gaia.embedded-setup'))
            ->assertStatus(200);
    }

    public function test_descriptor_is_audit_logged(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        $this->provisionClient($user);

        $this->actingAs($user)->get(route('gaia.embedded-setup', [
            'client_id' => config('gaia.client_id'),
            'chrometype' => 'chromebook',
            'mi' => 'ee', // unknown param must still be captured
        ]))->assertStatus(200);

        $activity = Activity::where('description', 'gaia device sign-in')->latest('id')->first();
        $this->assertNotNull($activity, 'a gaia device sign-in activity must be logged');
        $this->assertSame('ee', $activity->getExtraProperty('query.mi'));
        $this->assertSame('chromebook', $activity->getExtraProperty('query.chrometype'));
        $this->assertNotNull($activity->getExtraProperty('device_id'));
    }

    public function test_device_is_upserted_and_keyed_by_cookie(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        $this->provisionClient($user);

        // In tests, withCookie() values reach the controller verbatim (no cookie
        // decryption), so a stable device_id across both requests proves upsert.
        $deviceId = (string) Str::uuid();

        $this->actingAs($user)->withCookie('device_id', $deviceId)
            ->get(route('gaia.embedded-setup'))->assertStatus(200);
        $this->actingAs($user)->withCookie('device_id', $deviceId)
            ->get(route('gaia.embedded-setup'))->assertStatus(200);

        // Same device cookie => one row, updated in place.
        $this->assertDatabaseCount('chromeos_devices', 1);

        $device = ChromeosDevice::first();
        $this->assertSame($deviceId, $device->device_id);
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame($user->currentTeam->id, $device->team_id);
        $this->assertTrue($device->approved);
    }

    public function test_token_exchange_without_redirect_uri_succeeds_and_captures_token(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        [$client, $secret] = $this->provisionClient($user);

        $page = $this->actingAs($user)->get(route('gaia.embedded-setup'));
        $code = collect($page->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'oauth_code')?->getValue();
        $this->assertNotEmpty($code);

        // Device-built Chromium omits redirect_uri; the wrapper must default it.
        $token = $this->post('/oauth2/v4/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $client->id,
            'client_secret' => $secret,
        ]);

        $token->assertStatus(200);
        $token->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);

        $codeHash = hash('sha256', $code);
        $device = ChromeosDevice::where('last_code_hash', $codeHash)->first();
        $this->assertNotNull($device);

        $access = ChromeosDeviceToken::where('type', 'access')->where('code_hash', $codeHash)->first();
        $this->assertNotNull($access, 'the issued access token must be captured');
        $this->assertSame($device->id, $access->chromeos_device_id);
        $this->assertNotEmpty($access->jti);
        $this->assertSame(hash('sha256', $token->json('access_token')), $access->token_hash);

        $this->assertDatabaseHas('chromeos_device_tokens', ['type' => 'refresh', 'code_hash' => $codeHash]);
    }

    public function test_revoking_a_captured_token_blocks_userinfo(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        [$client, $secret] = $this->provisionClient($user);

        $page = $this->actingAs($user)->get(route('gaia.embedded-setup'));
        $code = collect($page->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'oauth_code')?->getValue();

        $token = $this->post('/oauth2/v4/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $client->id,
            'client_secret' => $secret,
        ]);
        $accessToken = $token->json('access_token');

        // Valid before revocation.
        $this->withToken($accessToken)->getJson(route('gaia.userinfo'))->assertStatus(200);

        $jti = ChromeosDeviceToken::where('type', 'access')->whereNotNull('jti')->value('jti');
        $this->assertNotEmpty($jti);
        $this->assertTrue(app(BlacklistChromeosToken::class)->revoke($jti));

        // Rejected after revocation (revoked=true is honored at auth:api).
        $this->withToken($accessToken)->getJson(route('gaia.userinfo'))->assertStatus(401);
        $this->assertDatabaseHas('chromeos_device_tokens', ['jti' => $jti, 'revoked' => true]);
    }
}
