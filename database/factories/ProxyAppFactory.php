<?php

namespace Database\Factories;

use App\Models\ProxyApp;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProxyApp>
 */
class ProxyAppFactory extends Factory
{
    public function definition(): array
    {
        return [
            'host' => $this->faker->unique()->domainName(),
            'name' => $this->faker->words(2, true),
            'team_id' => Team::factory(),
            'oauth_client_id' => null,
            'enabled' => true,
            'status' => ProxyApp::STATUS_APPROVED,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ProxyApp::STATUS_PENDING, 'enabled' => false]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ProxyApp::STATUS_REJECTED, 'enabled' => false]);
    }
}
