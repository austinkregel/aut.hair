<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserinfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(UserResource::make($request->user()));
    }
}
