<?php

namespace App\Http\Controllers;

use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogController
{
    public function __invoke()
    {
        return QueryBuilder::for(Activity::class)
            ->allowedFilters(['description', 'subject_type', 'subject_id'])
            ->allowedIncludes(['causer', 'subject'])
            ->allowedSorts(['id', 'name', 'causer_id', 'subject_id'])
            ->paginate()
            ->appends(request()->query());
    }
}
