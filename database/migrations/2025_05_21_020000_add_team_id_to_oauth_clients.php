<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->foreignId('team_id')
                ->nullable()
                ->after('user_id')
                ->index();
        });

        // Migrate existing clients from user ownership to team ownership where possible.
        DB::table('oauth_clients')
            ->whereNull('team_id')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(100, function ($clients) {
                foreach ($clients as $client) {
                    $teamId = DB::table('teams')
                        ->where('user_id', $client->user_id)
                        ->orderBy('id')
                        ->value('id');

                    if ($teamId) {
                        DB::table('oauth_clients')
                            ->where('id', $client->id)
                            ->update(['team_id' => $teamId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }
};

