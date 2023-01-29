<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class OauthLinkController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('Profile/LinkSocial', [

        ]);
    }
}
