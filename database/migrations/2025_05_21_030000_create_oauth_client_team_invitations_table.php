<?php

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
        Schema::create('oauth_client_team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviting_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('invited_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['inviting_team_id', 'invited_team_id', 'oauth_client_id'], 'oauth_client_team_invitation_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_client_team_invitations');
    }
};

