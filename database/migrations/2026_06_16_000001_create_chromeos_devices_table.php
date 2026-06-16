<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per openFyde/ChromeOS device, identified by a server-set `device_id`
     * cookie. This is a best-effort SOFT identity (resets on powerwash), not a
     * hardware id — real device identity would come from DM enrollment (out of
     * scope). Used for audit, the admin device list, and correlating issued tokens
     * back to the device that signed in (via `last_code_hash`).
     */
    public function up(): void
    {
        Schema::create('chromeos_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_id')->unique();
            $table->foreignIdFor(Team::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            // sha256 of the most-recently minted oauth_code — correlation key the
            // token endpoint uses to tie an issued token back to this device.
            $table->string('last_code_hash', 64)->nullable()->index();
            $table->string('last_seen_ip')->nullable();
            $table->text('last_user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            // Recording-first: defaults to config('gaia.auto_approve_devices').
            // No enforcement this slice (sign-in is never blocked).
            $table->boolean('approved')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chromeos_devices');
    }
};
