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
        Schema::table('socials', function (Blueprint $table) {
            $table->renameColumn('user_id', 'ownable_id');
            $table->string('ownable_type')->index()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ownable_socials', function (Blueprint $table) {
            //
        });
    }
};
