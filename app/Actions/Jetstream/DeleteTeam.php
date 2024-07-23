<?php

namespace App\Actions\Jetstream;

use Laravel\Jetstream\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
    /**
     * Delete the given team.
     *
     * @param  mixed  $team
     */
    public function delete($team): void
    {
        activity()->on($team)
            ->causedBy(request()->user())
            ->event('delete')
            ->log('deleted');
        $team->purge();
    }
}
