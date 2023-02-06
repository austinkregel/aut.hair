<?php

namespace App\Http\Controllers;

use Spatie\QueryBuilder\QueryBuilder;

class AbstractCrudController
{
    protected function newModel()
    {
        throw new \Exception('You must implement newModel() in your controller.');
    }

    public function index()
    {
        return QueryBuilder::for($this->newModel())
            ->allowedFilters($this->newModel()->getFillable())
            ->allowedIncludes(['user'])
            ->allowedSorts(['id', 'name'])
            ->paginate()
            ->appends(request()->query());
    }
}
