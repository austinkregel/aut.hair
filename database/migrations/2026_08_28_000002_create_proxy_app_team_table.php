<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additional teams granted access to a proxy app, mirroring
     * oauth_client_team_invitations for OAuth clients.
     */
    public function up(): void
    {
        Schema::create('proxy_app_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxy_app_id')->constrained('proxy_apps')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['proxy_app_id', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxy_app_team');
    }
};
