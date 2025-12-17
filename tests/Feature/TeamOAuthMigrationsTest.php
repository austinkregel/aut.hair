<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamOAuthMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_codes_and_tokens_have_team_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('oauth_auth_codes', 'team_id'));
        $this->assertTrue(Schema::hasColumn('oauth_access_tokens', 'team_id'));
    }

    public function test_oauth_clients_have_grant_types_and_scopes_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('oauth_clients', 'grant_types'));
        $this->assertTrue(Schema::hasColumn('oauth_clients', 'scopes'));
    }
}

