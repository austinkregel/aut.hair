<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_seeds', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            // Stored encrypted via the SyncSeed model's `encrypted` cast (APP_KEY).
            // Holds the user's sync-chain seed material (canonical entropy).
            $table->text('seed');
            $table->timestamps();

            // One sync-chain seed per user (shared across all their devices).
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_seeds');
    }
};
