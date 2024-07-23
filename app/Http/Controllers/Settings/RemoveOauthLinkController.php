<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Social;

class RemoveOauthLinkController extends Controller
{
    public function __invoke(): Response
    {
        request()->validate([
            'social_id' => 'exists:socials,id',
        ]);

        $social = Social::with(['ownable', 'owner'])->find(request()->get('social_id'));

        abort_unless(isset($social->ownable), 404);

        abort_unless($social->ownable->is(auth()->user()), 404);

        $social->delete();

        return response('', 204);
    }
}
