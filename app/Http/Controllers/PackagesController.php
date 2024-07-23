<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PackagesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(cache()
            ->remember('packages', now()->addMinutes(30), fn () => json_decode(file_get_contents(storage_path('provider-information.json')), true))
        );
    }
}
