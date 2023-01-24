<?php

namespace App\Http\Controllers\Auth\Team;

use App\Http\Controllers\Controller;
use App\Models\Social;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class CallbackController extends Controller
{
    public function __invoke($provider)
    {
        try {
            Socialite::driver($provider);
        } catch (\InvalidArgumentException $e) {
            abort(404);
        }

        if ($provider === 'synology' && empty(request()->get('access_token', null))) {
            return view('synology');
        }

        try {
            $user = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect('/login?message='.urlencode($e->getMessage()));
        }

        $social = Social::with('owner')->firstWhere([
            'provider' => $provider,
            'provider_id' => $user->getId(),
        ]);

        // Don't create a new user. Link the user to their existing account.
        $localUser = auth()->user() ?? $social->owner;

        if (empty($localUser)) {
            return redirect('/login?message='.urlencode('You need to register first.'));
        }

        if (empty($social) || !$localUser->is($social?->owner)) {
            auth()->login($localUser, true);

            $social = Social::create([
                'ownable_id' => $localUser->id,
                'ownable_type' => get_class($localUser),
                'email' => $user->getEmail(),
                'provider' => $provider,
                'provider_id' => $user->getId(),
                'expires_at' => now()->addSeconds($user->expiresIn),
            ]);

            event(new Registered($localUser));
            $social->load('user');
        } else {
            auth()->login($social->owner, true);
            $social->update([
                'email' => $user->getEmail(),
                'expires_at' => now()->addSeconds($user->expiresIn),
            ]);
        }

        activity()
            ->performedOn($social)
            ->causedBy($social->owner)
            ->log('logged in');

        return redirect('/dashboard');
    }
}
