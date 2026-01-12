<?php

namespace Tests\Feature;

use App\Http\Controllers\AccessTokenController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response as Psr7Response;
use Tests\TestCase;

class AccessTokenTeamContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_when_auth_code_missing_team_id(): void
    {
        DB::table('oauth_auth_codes')->insert([
            'id' => 'code-no-team',
            'user_id' => 1,
            'client_id' => 1,
            'scopes' => '[]',
            'revoked' => 0,
            'expires_at' => now()->addMinute(),
        ]);

        $psr = (new \Nyholm\Psr7\ServerRequest('POST', '/oauth/token'))
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'code' => 'code-no-team',
                'client_id' => 1,
            ]);

        $response = app(AccessTokenController::class)->issueToken($psr);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('team_id is required', $response->getContent());
    }

    public function test_sets_team_id_on_access_token_when_present(): void
    {
        DB::table('oauth_auth_codes')->insert([
            'id' => 'code-team',
            'user_id' => 1,
            'client_id' => 1,
            'team_id' => 7,
            'scopes' => '[]',
            'revoked' => 0,
            'expires_at' => now()->addMinute(),
        ]);

        DB::table('oauth_access_tokens')->insert([
            'id' => 'jti123',
            'user_id' => 1,
            'client_id' => 1,
            'name' => 'test',
            'scopes' => '[]',
            'revoked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenPayload = base64_encode(json_encode(['alg' => 'none']));
        $accessPayload = base64_encode(json_encode(['jti' => 'jti123']));
        $fakeToken = rtrim(strtr($tokenPayload, '+/', '-_'), '=').'.'.
            rtrim(strtr($accessPayload, '+/', '-_'), '=').'.sig';

        $this->mock(AuthorizationServer::class, function ($mock) use ($fakeToken) {
            $psrResponse = new Psr7Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'access_token' => $fakeToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ])
            );

            $mock->shouldReceive('respondToAccessTokenRequest')
                ->andReturn($psrResponse);
        });

        $psr = (new \Nyholm\Psr7\ServerRequest('POST', '/oauth/token'))
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'code' => 'code-team',
                'client_id' => 1,
            ]);

        $response = app(AccessTokenController::class)->issueToken($psr);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(7, DB::table('oauth_access_tokens')->where('id', 'jti123')->value('team_id'));
    }
}
