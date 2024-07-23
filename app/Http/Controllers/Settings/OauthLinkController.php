<?php

namespace App\Http\Controllers\Settings;

use Inertia\Response;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class OauthLinkController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Profile/LinkSocial', [

        ]);
    }
}
