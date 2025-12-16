<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserinfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->tokenCan('openid')) {
            return response()->json(['error' => 'insufficient_scope'], 403);
        }

        return response()->json(UserResource::make($request->user()));
    }
}
