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
            // Use text for broad DB compatibility (sqlite/json support differs).
            $table->text('grant_types')->nullable();
            $table->text('scopes')->nullable();
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

