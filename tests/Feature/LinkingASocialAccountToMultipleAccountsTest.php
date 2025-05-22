<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\AbstractProvider;
use Tests\TestCase;

class LinkingASocialAccountToMultipleAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_verified_users_can_oauth_non_verified_users_cannot_oauth(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->markEmailAsVerified();

        $user->socials()->create([
            'provider' => 'fake-hub',
            'provider_id' => 31038482,
            'email' => 'fake@example.com',
            'name' => 'John Smith',
            'expires_at' => now()->addHour(),
        ]);
        $this->setupFakeSocialiteDriver($user);

        $this->assertDatabaseHas('socials', [
            'ownable_id' => $user->id,
            'ownable_type' => User::class,
            'provider' => 'fake-hub',
        ]);

        $this->assertDatabaseCount('socials', 1);

        $user = User::factory()->create();

        $response = $this->get('/callback/fake-hub', []);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseMissing('socials', [
            'ownable_id' => $user->id,
            'ownable_type' => User::class,
            'provider' => 'fake-hub',
        ]);

        $this->assertDatabaseCount('socials', 1);
    }

    protected function setupFakeSocialiteDriver(?User $expectedUser)
    {
        /** @var SocialiteManager $socialite */
        $socialite = $this->app->make(SocialiteFactory::class);

        $socialite->extend('fake-hub', function () use ($expectedUser) {
            $mockProvider = \Mockery::mock(AbstractProvider::class);
            $mockedUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
            if (isset($expectedUser)) {
                $mockedUser->shouldReceive('getId')->once()->andReturn(31038482);
                $mockedUser->shouldReceive('getEmail')->once()->andReturn('fake@example.com');
            } else {
                $mockedUser->shouldReceive('getId')->once()->andReturn(489284);
                $mockedUser->shouldReceive('getName')->once()->andReturn('John Smith');
                $mockedUser->shouldReceive('getEmail')->once()->andReturn('John Smith');
            }
            $mockProvider->shouldReceive('stateless')->once()->andReturnSelf();
            $mockProvider->shouldReceive('user')->once()->andReturn($mockedUser);

            return $mockProvider;
        });

        return $socialite;
    }
}
