<?php

namespace App\Http\Controllers;

use App\Models\Social;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'currentTeam' => request()->user()->currentTeam,
            'activityItems' => QueryBuilder::for(Activity::class)
                ->allowedFilters(['description', 'subject_type', 'subject_id'])
                ->allowedIncludes(['causer', 'subject'])
                ->allowedSorts(['id', 'name', 'causer_id', 'subject_id'])
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->where('causer_id', auth()->id())
                            ->where('causer_type', User::class);
                    })
                        ->orWhere(function ($q) {
                            $q->where('causer_id', auth()->user()?->currentTeam?->id)
                                ->where('causer_type', \App\Models\Team::class);
                        });
                })
                ->where('description', 'logged in')
                ->with('causer', 'subject')
                ->defaultSort('-id')
                ->paginate()
                ->appends(request()->query()),
            'creationItems' => QueryBuilder::for(Activity::class)
                ->allowedFilters(['description', 'subject_type', 'subject_id'])
                ->allowedIncludes(['causer', 'subject'])
                ->allowedSorts(['id', 'name', 'causer_id', 'subject_id'])
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->where('causer_id', auth()->id())
                            ->where('causer_type', User::class);
                    })
                        ->orWhere(function ($q) {
                            $q->where('causer_id', auth()->user()?->currentTeam?->id)
                                ->where('causer_type', \App\Models\Team::class);
                        });
                })
                ->where('description', '!=', 'logged in')
                ->with('causer', 'subject')
                ->defaultSort('-id')
                ->paginate()
                ->appends(request()->query()),
            'users_count' => User::count(),
            'social_count' => Social::count(),
            'login_count' => Activity::where('description', 'logged in')->count(),
            'clients_count' => \DB::table('oauth_clients')->count() + \DB::table('oauth_personal_access_clients')->count(),
        ]);
    }

    public function link()
    {
        return Inertia::location('/login');
    }
}
