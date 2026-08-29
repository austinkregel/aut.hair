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
        Schema::create('proxy_apps', function (Blueprint $table) {
            $table->id();
            // The hostname Traefik forwards as X-Forwarded-Host for an
            // sso_protected app. This is how the verify endpoint identifies
            // which app is being accessed (there is no client_id in forward auth).
            $table->string('host')->unique();
            $table->string('name');
            // Owning team. Access is granted to this team plus any team in the
            // proxy_app_team pivot, mirroring the oauth_clients ownership model.
            // Nullable: a discovered/pending app has no owner until it is approved.
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            // Optional link to a Passport client for apps that are also OIDC clients.
            $table->foreignId('oauth_client_id')->nullable()->constrained('oauth_clients')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxy_apps');
    }
};
