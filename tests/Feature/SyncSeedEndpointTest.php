<?php

namespace Tests\Feature;

use App\Models\SyncSeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SyncSeedEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'passport.public_key' => base_path('tests/Feature/test-public.key'),
            'passport.private_key' => base_path('tests/Feature/test-private.key'),
        ]);
    }

    public function test_sync_seed_requires_sync_scope(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Passport::actingAs($user, ['openid']); // missing sync

        $response = $this->postJson(route('sync.seed'));

        $response->assertStatus(403);
        $response->assertJson(['error' => 'insufficient_scope']);
    }

    public function test_sync_seed_is_created_on_first_call(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Passport::actingAs($user, ['sync']);

        $response = $this->postJson(route('sync.seed'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['seed', 'encoding', 'bits']);
        $response->assertJson(['encoding' => 'hex', 'bits' => 128]);

        // 128 bits = 16 bytes = 32 hex chars.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $response->json('seed'));
        $this->assertDatabaseCount('sync_seeds', 1);
        $this->assertDatabaseHas('sync_seeds', ['user_id' => $user->id]);
    }

    public function test_sync_seed_is_idempotent_and_stable_per_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Passport::actingAs($user, ['sync']);

        $first = $this->postJson(route('sync.seed'))->json('seed');
        $second = $this->postJson(route('sync.seed'))->json('seed');

        $this->assertSame($first, $second, 'Repeated calls must return the same seed.');
        $this->assertDatabaseCount('sync_seeds', 1);
    }

    public function test_sync_seed_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Passport::actingAs($user, ['sync']);

        $plain = $this->postJson(route('sync.seed'))->json('seed');

        // Re-reading through the model decrypts and matches what the API returned.
        $this->assertSame($plain, $user->fresh()->syncSeed->seed);
        // The raw DB column must NOT contain the plaintext seed (encrypted cast).
        $this->assertNotSame($plain, $this->rawColumn('sync_seeds', 'seed', $user->id));
    }

    private function rawColumn(string $table, string $column, int $userId): string
    {
        return (string) \Illuminate\Support\Facades\DB::table($table)
            ->where('user_id', $userId)
            ->value($column);
    }
}
