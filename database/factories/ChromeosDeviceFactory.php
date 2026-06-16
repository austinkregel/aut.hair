<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChromeosDevice>
 */
class ChromeosDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_id' => (string) Str::uuid(),
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'last_code_hash' => hash('sha256', Str::random(40)),
            'last_seen_ip' => $this->faker->ipv4(),
            'last_user_agent' => 'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0)',
            'last_seen_at' => now(),
            'approved' => true,
        ];
    }
}
