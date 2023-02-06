<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function testOnlyHostMiddlewarePreventsAccessUnlessTheEmailMatchesTheConfigValue()
    {
        config(['auth.admin_emails' => ['real-email@fake.tools']]);
        $user = User::factory()->create([
            'email' => 'never-a-real-email@not.fake.tools'
        ]);

        $this->actingAs($user)
            ->get('/user/admin')
            ->assertStatus(404);
    }

    public function testApiRoutesAreBlockedIfWeAreNotAdmin()
    {
        config(['auth.admin_emails' => ['real-email@fake.tools']]);
        $user = User::factory()->create([
            'email' => 'never-a-real-email@not.fake.tools'
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

    public function testOnlyHostMiddlewareAllowsAccessUnlessTheEmailMatchesTheConfigValue()
    {
        config(['auth.admin_emails' => ['real-email@fake.tools']]);

        $user = User::factory()->create([
            'email' => 'real-email@fake.tools'
        ]);

        $this->actingAs($user)
            ->get('/user/admin')
            ->assertStatus(200);
    }
}
