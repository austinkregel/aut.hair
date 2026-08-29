<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the registration lifecycle to proxy apps.
     *
     * status: pending (discovered or deploy-registered, not yet trusted) ->
     * approved (an admin, or a trusted machine token, has vetted it) / rejected.
     * Only approved AND enabled apps ever return 200 at the verify endpoint.
     *
     * discovered_at / requested_by capture first-contact discovery: the user whose
     * request first surfaced an unregistered host (null when a machine registers it).
     */
    public function up(): void
    {
        Schema::table('proxy_apps', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('enabled')->index();
            $table->timestamp('discovered_at')->nullable()->after('status');
            $table->foreignId('requested_by')->nullable()->after('discovered_at')
                ->constrained('users')->nullOnDelete();
        });

        // Rows that predate the lifecycle were implicitly active; keep them so.
        DB::table('proxy_apps')->update(['status' => 'approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proxy_apps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn(['status', 'discovered_at']);
        });
    }
};
