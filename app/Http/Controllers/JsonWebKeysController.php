<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class JsonWebKeysController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'keys' => [
                [
                    'kty' => 'sig',
                    'alg' => 'HS256',
                ],
            ],
        ]);
    }
}
