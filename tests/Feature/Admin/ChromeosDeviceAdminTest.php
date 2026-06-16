<?php

namespace Tests\Feature\Admin;

use App\Models\ChromeosDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Read-only admin view of ChromeOS devices — gated by OnlyHost
 * (config('auth.admin_emails')), mirroring AdminRouteAccessTest.
 */
class ChromeosDeviceAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_view_the_device_list(): void
    {
        config(['auth.admin_emails' => ['admin@aut.hair']]);
        $user = User::factory()->create(['email' => 'someone@else.test']);

        $this->actingAs($user)
            ->get(route('admin.chromeos-devices'))
            ->assertStatus(404);
    }

    public function test_admin_sees_devices_in_the_inertia_page(): void
    {
        config(['auth.admin_emails' => ['admin@aut.hair']]);
        $admin = User::factory()->create(['email' => 'admin@aut.hair']);
        ChromeosDevice::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.chromeos-devices'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ChromeosDevices')
                ->has('devices.data', 1)
            );
    }
}
