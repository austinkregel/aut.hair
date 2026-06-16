<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * `cros:setup` — provisions the confidential ChromeOS OAuth client for openFyde
 * GAIA sign-in. (Slice 2b-i.)
 */
class CrosSetupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_confidential_chromeos_client_for_the_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam->id;

        $this->artisan('cros:setup', ['--team' => $teamId])->assertSuccessful();

        $client = Client::where('name', 'openFyde ChromeOS')->first();

        $this->assertNotNull($client, 'cros:setup must create the ChromeOS client');
        $this->assertEquals($teamId, $client->team_id);
        $this->assertTrue($client->confidential(), 'client must be confidential (have a secret)');
        $this->assertContains('authorization_code', $client->grant_types);
        $this->assertContains('refresh_token', $client->grant_types);
        $this->assertContains('https://www.googleapis.com/auth/chromesync', $client->scopes);
    }

    public function test_it_is_idempotent_without_force(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam->id;

        $this->artisan('cros:setup', ['--team' => $teamId])->assertSuccessful();
        $this->artisan('cros:setup', ['--team' => $teamId])->assertSuccessful();

        $this->assertEquals(
            1,
            Client::where('name', 'openFyde ChromeOS')->where('revoked', false)->count(),
            're-running without --force must not create a second client'
        );
    }

    public function test_force_rotates_the_client(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam->id;

        $this->artisan('cros:setup', ['--team' => $teamId])->assertSuccessful();
        $first = Client::where('name', 'openFyde ChromeOS')->where('revoked', false)->first();

        $this->artisan('cros:setup', ['--team' => $teamId, '--force' => true])->assertSuccessful();
        $second = Client::where('name', 'openFyde ChromeOS')->where('revoked', false)->first();

        $this->assertNotEquals($first->id, $second->id, '--force must create a fresh client');
        $this->assertTrue(Client::find($first->id)->revoked, 'the old client must be revoked');
    }

    public function test_it_fails_without_a_team(): void
    {
        // No teams exist (RefreshDatabase, no seeding) -> the command should error.
        $this->artisan('cros:setup')->assertFailed();
    }
}
