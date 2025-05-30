<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class DeleteApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tokens_can_be_deleted(): void
    {
        if (! Features::hasApiFeatures()) {
            $this->markTestSkipped('API support is not enabled.');

            return;
        }

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $clientId = app(ClientRepository::class)->createPersonalAccessClient(
            $user->id,
            'Personal Access Client',
            (string) url('')
        )->id;
        $token = $user->tokens()->create([
            'id' => Str::uuid(),
            'name' => 'Test Token',
            'token' => Str::random(40),
            'abilities' => ['create', 'read'],
            'revoked' => false,
            'client_id' => $clientId,
        ]);

        $response = $this->delete('/user/api-tokens/'.$token->id);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
