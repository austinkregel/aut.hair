<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JsonWebKeysController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'keys' => [
                [
                    'kty' => 'sig',
                    'alg' => 'HS256'
                ]
            ]
        ]);
    }
}
