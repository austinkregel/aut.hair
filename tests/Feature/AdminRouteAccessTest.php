<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_host_middleware_prevents_access_unless_the_email_matches_the_config_value(): void
    {
        config(['auth.admin_emails' => ['real-email@fake.tools']]);
        $user = User::factory()->create([
            'email' => 'never-a-real-email@not.fake.tools',
        ]);

        $this->actingAs($user)
            ->get('/user/admin')
            ->assertStatus(404);
    }

    public function test_api_routes_are_blocked_if_we_are_not_admin(): void
    {
        config(['auth.admin_emails' => ['real-email@fake.tools']]);
        $user = User::factory()->create([
            'email' => 'never-a-real-email@not.fake.tools',
        ]);

        $this->actingAs($user)
            ->post('/api/install', [
                'name' => 'socaliteproviders/google',
            ])
            ->assertStatus(404);
        $this->actingAs($user)
            ->post('/api/uninstall', [
                'name' => 'socaliteproviders/google',
            ])
            ->assertStatus(404);
        $this->actingAs($user)
            ->post('/api/disable', [
                'name' => 'socaliteproviders/google',
            ])
            ->assertStatus(404);
        $this->actingAs($user)
            ->post('/api/enable', [
                'name' => 'socaliteproviders/google',
            ])
            ->assertStatus(404);
    }

    public function test_only_host_middleware_allows_access_unless_the_email_matches_the_config_value(): void
    {
        config(['auth.admin_emails' => ['real-email@fake.tools']]);

        $user = User::factory()->create([
            'email' => 'real-email@fake.tools',
        ]);

        $this->actingAs($user)
            ->get('/user/admin')
            ->assertStatus(200);
    }
}
