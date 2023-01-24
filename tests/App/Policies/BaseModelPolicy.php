<?php

declare(strict_types=1);

namespace Tests\App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Tests\App\BasicModel;

class BaseModelPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, BasicModel $team)
    {
        return $user->belongsToTeam($team);
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, BasicModel $team)
    {
        return $user->ownsTeam($team);
    }

    public function addTeamMember(User $user, BasicModel $team)
    {
        return $user->ownsTeam($team);
    }

    public function updateTeamMember(User $user, BasicModel $team)
    {
        return $user->ownsTeam($team);
    }

    public function removeTeamMember(User $user, BasicModel $team)
    {
        return $user->ownsTeam($team);
    }

    public function delete(User $user, BasicModel $team)
    {
        return $user->ownsTeam($team);
    }
}
