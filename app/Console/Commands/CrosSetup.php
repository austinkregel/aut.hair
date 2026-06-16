<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * `cros:setup` — provision everything aut.hair needs for openFyde (ChromeOS)
 * GAIA sign-in:
 *
 *   - a CONFIDENTIAL "ChromeOS" Passport client (authorization_code + refresh
 *     grants, the GAIA scopes from config/gaia.php), tied to a team;
 *   - the exact env values to wire both sides (this app's .env and the build
 *     host's openFyde/auth.env).
 *
 * The GAIA scopes themselves live in config/openid.php (tokens_can) and are
 * already registered; this command provisions the client that uses them.
 *
 * Idempotent: re-running reports the existing client. The secret is shown only
 * at creation (Passport hashes it at rest) — use --force to rotate by recreating.
 */
class CrosSetup extends Command
{
    protected $signature = 'cros:setup
        {--name=openFyde ChromeOS : OAuth client display name}
        {--team= : Team id to own the client (default: first team)}
        {--redirect= : Redirect URI (default: config gaia.redirect_uri)}
        {--force : Recreate the client if it exists (rotates the secret)}';

    protected $description = 'Provision the aut.hair side of openFyde GAIA sign-in (confidential ChromeOS client)';

    public function handle(ClientRepository $clients): int
    {
        $name = (string) $this->option('name');
        $redirect = (string) ($this->option('redirect') ?: config('gaia.redirect_uri'));
        $teamId = $this->option('team') ?: DB::table('teams')->orderBy('id')->value('id');

        if (! $teamId) {
            $this->error('No team found. Create a team in aut.hair first, or pass --team=<id>.');

            return self::FAILURE;
        }

        $existing = Client::where('name', $name)->where('revoked', false)->first();

        if ($existing && ! $this->option('force')) {
            $this->warn("Client \"{$name}\" already exists (id {$existing->id}).");
            $this->line('The secret is only shown at creation; re-run with --force to rotate it.');
            $this->printEnv($existing->id, '<unchanged — use --force to reveal a new one>', $redirect);

            return self::SUCCESS;
        }

        if ($existing) {
            $existing->forceFill(['revoked' => true])->save();
            $this->warn("Revoked existing client {$existing->id} (--force).");
        }

        // Mirror ClientController@store: confidential client, then team + grants.
        $client = $clients->create(null, $name, $redirect, null, false, false, true);
        $client->team_id = $teamId;
        $client->user_id = null;
        $client->save();
        $client->forceFill([
            'grant_types' => ['authorization_code', 'refresh_token'],
            'scopes' => config('gaia.scopes'),
        ])->save();

        $this->info("Registered confidential ChromeOS client (team {$teamId}).");
        $this->printEnv($client->id, (string) $client->plainSecret, $redirect);

        return self::SUCCESS;
    }

    private function printEnv(string $clientId, string $secret, string $redirect): void
    {
        $this->newLine();
        $this->line('# aut.hair — set in .env:');
        $this->line("GAIA_CHROMEOS_CLIENT_ID={$clientId}");
        $this->line("GAIA_CHROMEOS_REDIRECT_URI={$redirect}");
        $this->newLine();
        $this->line('# build host — set in openFyde/auth.env, then `./build.sh image`:');
        $this->line("OPENFYDE_OAUTH_CLIENT_ID={$clientId}");
        $this->line("OPENFYDE_OAUTH_CLIENT_SECRET={$secret}");
    }
}
