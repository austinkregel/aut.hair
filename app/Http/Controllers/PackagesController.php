<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PackagesController extends Controller
{
    public function __invoke()
    {
        return response()->json(cache()
            ->remember('packages', now()->addMinutes(30), fn () => json_decode(file_get_contents(storage_path('provider-information.json')), true))
        );
    }
}
