<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database schema.
     *
     * @var \Illuminate\Database\Schema\Builder
     */
    protected $schema;

    public function __construct()
    {
        $this->schema = Schema::connection($this->getConnection());
    }

    public function up(): void
    {
        $this->schema->table('oauth_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('oauth_clients', 'grant_types')) {
                $table->text('grant_types')->nullable();
            }
            if (!Schema::hasColumn('oauth_clients', 'scopes')) {
                $table->text('scopes')->nullable();
            }
        });
    }

    public function down(): void
    {
        $this->schema->table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['grant_types', 'scopes']);
        });
    }

    public function getConnection(): ?string
    {
        return config('passport.storage.database.connection');
    }
};
