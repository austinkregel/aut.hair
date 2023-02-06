<?php

namespace App\Http\Controllers;

class JsonWebKeysController extends Controller
{
    public function __invoke()
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
