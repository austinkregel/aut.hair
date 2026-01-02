<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreateApiTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::tokensCan(array_fill_keys([
            'read', 'write', 'delete', 'create', 'update',
        ], ''));
    }

    public function test_api_tokens_can_be_created(): void
    {
        if (! Features::hasApiFeatures()) {
            $this->markTestSkipped('API support is not enabled.');

            return;
        }
        /** @var User $user */
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());
        $clientRepo = $this->app->make(ClientRepository::class);
        $client = $clientRepo->createPersonalAccessClient($user->id, 'Random thing', 'http://localhost');
        $response = $this->post('/user/api-tokens', [
            'id' => Str::uuid(),
            'name' => 'Test Token',
            'client_id' => $client->id,
            'revoked' => false,
            'permissions' => [
                'read',
                'update',
            ],
        ]);

        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertEquals('Test Token', $user->fresh()->tokens->first()->name);
        $this->assertSame('cc', $user->fresh()->tokens->first());
        $this->assertTrue($user->fresh()->tokens->first()->can('read'));
        $this->assertFalse($user->fresh()->tokens->first()->can('delete'));
    }
}
