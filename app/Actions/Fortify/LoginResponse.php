<?php

namespace App\Actions\Fortify;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Http\Responses\LoginResponse as LoginResponseBase;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse extends LoginResponseBase
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse(Request $request): Response
    {
        if ($request->session()->has('url.intended')) {
            return Inertia::location(session('url.intended'));
        }

        return parent::toResponse($request);
    }
}
