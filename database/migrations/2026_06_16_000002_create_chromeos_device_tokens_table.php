<?php

use App\Models\ChromeosDevice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only audit of tokens issued to a ChromeOS device sign-in. A device
     * signs in repeatedly and refresh rotation issues many access tokens, so this
     * is one-to-many. We never store the raw token — only its sha256 hash plus the
     * `jti` (== oauth_access_tokens.id), which is what an admin revoke targets.
     */
    public function up(): void
    {
        Schema::create('chromeos_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ChromeosDevice::class)->nullable()->constrained()->cascadeOnDelete();
            // jti of an access token == oauth_access_tokens.id (revoke target).
            // Null for refresh tokens (opaque, not a JWT). Unique so a duplicate
            // capture (e.g. retry) can't produce two rows for the same token.
            $table->string('jti')->nullable()->unique();
            $table->string('token_hash', 64);
            $table->string('type'); // access | refresh
            // sha256 of the oauth_code presented at the token endpoint — links the
            // token back to the device row whose last_code_hash matches.
            $table->string('code_hash', 64)->nullable()->index();
            $table->boolean('revoked')->default(false);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chromeos_device_tokens');
    }
};
