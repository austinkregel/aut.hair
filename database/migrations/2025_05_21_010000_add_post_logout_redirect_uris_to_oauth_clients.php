<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->text('post_logout_redirect_uris')->nullable()->after('redirect');
        });
    }

    public function down()
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('post_logout_redirect_uris');
        });
    }
};
