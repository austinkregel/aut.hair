<?php

namespace Database\Factories;

use App\Models\ChromeosDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChromeosDeviceToken>
 */
class ChromeosDeviceTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chromeos_device_id' => ChromeosDevice::factory(),
            'jti' => (string) Str::uuid(),
            'token_hash' => hash('sha256', Str::random(64)),
            'type' => 'access',
            'code_hash' => hash('sha256', Str::random(40)),
            'revoked' => false,
            'issued_at' => now(),
        ];
    }
}
