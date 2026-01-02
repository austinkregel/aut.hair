<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineTokenUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_use_client_credentials_access_token_to_call_machine_info_with_openid_scope(): void
    {
        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);

        $user = \App\Models\User::factory()->withPersonalTeam()->create([
            'email_verified_at' => now(),
        ]);

        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team, 'Test user must have a team.');

        $client = app(\Laravel\Passport\ClientRepository::class)->create(
            $user->id,
            'Machine Client',
            'http://localhost/callback',
        );

        // Our app associates clients to teams and uses JSON columns to describe allowed grants/scopes.
        $client->forceFill([
            'team_id' => $team->id,
            'grant_types' => ['client_credentials'],
            'scopes' => ['openid'],
        ])->save();

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'scope' => 'openid',
        ]);

        $tokenResponse->assertStatus(200);
        $data = $tokenResponse->json();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayNotHasKey('id_token', $data, 'client_credentials must not issue an ID token.');

        $machineInfo = $this
            ->withHeader('Authorization', 'Bearer '.$data['access_token'])
            ->getJson(route('oidc.machine_info'));

        $machineInfo
            ->assertStatus(200)
            ->assertJson([
                'client_id' => (string) $client->id,
                'name' => 'Machine Client',
            ]);

        $this->assertContains('openid', $machineInfo->json('scopes') ?? []);
    }

    public function test_machine_info_requires_openid_scope(): void
    {
        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);

        $user = \App\Models\User::factory()->withPersonalTeam()->create([
            'email_verified_at' => now(),
        ]);

        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team, 'Test user must have a team.');

        $client = app(\Laravel\Passport\ClientRepository::class)->create(
            $user->id,
            'Machine Client (No OpenID)',
            'http://localhost/callback',
        );

        $client->forceFill([
            'team_id' => $team->id,
            'grant_types' => ['client_credentials'],
            'scopes' => ['profile'],
        ])->save();

        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            // Request a non-openid scope so machine-info must reject it.
            'scope' => 'profile',
        ]);

        $tokenResponse->assertStatus(200);
        $accessToken = $tokenResponse->json('access_token');
        $this->assertNotEmpty($accessToken);

        $machineInfo = $this
            ->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson(route('oidc.machine_info'));

        $machineInfo
            ->assertStatus(403)
            ->assertJson([
                'error' => 'insufficient_scope',
            ]);
    }
}


