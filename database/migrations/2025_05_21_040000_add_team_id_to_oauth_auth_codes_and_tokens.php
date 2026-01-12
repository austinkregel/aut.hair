<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->index();
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }
};

