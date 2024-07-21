<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRouteTest extends TestCase
{
    use RefreshDatabase;

    public function testUsersRouteSuccess()
    {
        $this->artisan('passport:client', [
            '--personal' => true,
            '--name' => 'personal'
        ]);
        /** @var User $user */
        $user = User::factory()->create();
        $token = $user->createToken('Passport Token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->token->accessToken
        ])->getJson('api/user');

        $response->assertStatus(200);
    }
    public function testUserInfoRouteSuccessForOauth()
    {
        $this->artisan('passport:client', [
            '--personal' => true,
            '--name' => 'personal'
        ]);
        /** @var User $user */
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('Passport Token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->token->accessToken
        ])->getJson('api/userinfo');

        $response->assertStatus(200);
    }
    public function testUserInfoRouteFailsForNotBeingVerified()
    {
        $this->artisan('passport:client', [
            '--personal' => true,
            '--name' => 'personal'
        ]);
        /** @var User $user */
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        $token = $user->createToken('Passport Token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->token->accessToken
        ])->getJson('api/userinfo');

        $response->assertStatus(403);
    }
}