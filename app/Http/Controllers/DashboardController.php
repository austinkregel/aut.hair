<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;

class DashboardController extends Controller
{
    public function __invoke() {
        return Inertia::render('Dashboard', [
            'currentTeam' => request()->user()->currentTeam,
            'activityItems' => QueryBuilder::for(Activity::class)
                ->allowedFilters(['description', 'subject_type', 'subject_id'])
                ->allowedIncludes(['causer', 'subject'])
                ->allowedSorts(['id', 'name' ,'causer_id', 'subject_id'])
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
                ->with('causer', 'subject')
                ->defaultSort('-id')
                ->paginate()
                ->appends(request()->query())
        ]);
    }

    public function link()
    {
        return Inertia::location('/login');
    }
}
