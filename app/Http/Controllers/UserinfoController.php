<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Repositories\KeyRepositoryContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserinfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(UserResource::make($request->user()));
    }
}
