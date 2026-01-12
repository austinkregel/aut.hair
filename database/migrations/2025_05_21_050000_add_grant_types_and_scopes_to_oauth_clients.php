<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('oauth_clients', 'grant_types')) {
                $table->json('grant_types')->nullable()->after('redirect');
            }
            if (!Schema::hasColumn('oauth_clients', 'scopes')) {
                $table->json('scopes')->nullable()->after('grant_types');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['grant_types', 'scopes']);
        });
    }
};

