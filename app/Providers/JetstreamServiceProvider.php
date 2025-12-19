<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use App\Models\Token;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;
use Laravel\Passport\Passport;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Passport::useTokenModel(Token::class);
        // Define Passport scopes (used by /oauth/scopes and token issuance).
        // Must be an associative array: scope => description.
        $scopes = config('openid.passport.tokens_can', []);
        Passport::tokensCan($scopes);

        $tokenPermissions = array_keys($scopes);
        Jetstream::permissions($tokenPermissions);
        Jetstream::defaultApiTokenPermissions(in_array('openid', $tokenPermissions, true) ? ['openid'] : array_slice($tokenPermissions, 0, 1));

        Jetstream::role('admin', 'Administrator', [
            'create',
            'read',
            'update',
            'delete',
        ])->description('Administrator users can perform any action.');
        Jetstream::role('admin', 'Manager', [
            'create',
            'read',
            'update',
        ])->description('Manager users can link new users.');

        Jetstream::role('login', 'Login', [
            'read',
        ])->description('Users with this permission can authenticate to any of the team owner\'s apps.');
    }
}
