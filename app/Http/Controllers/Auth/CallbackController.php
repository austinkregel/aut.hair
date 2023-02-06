<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Social;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class CallbackController extends Controller
{
    public function __invoke($provider)
    {
//        try {
//            Socialite::driver($provider);
//        } catch (\InvalidArgumentException $e) {
//            abort(404);
//        }

        if ($provider === 'synology' && empty(request()->get('access_token', null))) {
            // Synology requires the usage of their custom javascript, which can be loaded
            // from the NAS's domain. I tried building a redirect URL but wasn't able to reproduce
            // the correct redirect, so to work around we just want to load up a basic html page.
            return view('synology');
        }

        try {
            $user = Socialite::driver($provider)->stateless()->user();

        } catch (InvalidStateException $e) {
            $user = Socialite::driver($provider)->stateless()->user();
        } catch (\Throwable $e) {
            return redirect('/login?message='.urlencode($e->getMessage()));
        }

        $social = Social::with('owner')->firstWhere([
            'provider' => $provider,
            'provider_id' => $user->getId(),
        ]);

        // Don't create a new user. Link the user to their existing account.
        $localUser = $social->ownable ?? auth()->user();

        if (empty($localUser)) {
            return redirect('/login?message='.urlencode('You need to register first.'));
        }

        if (empty($social)) {
            auth()->login($localUser, true);

            $social = Social::create([
                'ownable_id' => $localUser->id,
                'ownable_type' => User::class,
                'email' => $user->getEmail(),
                'provider' => $provider,
                'provider_id' => $user->getId(),
                'expires_at' => now()->addSecond($user->expiresIn),
            ]);

            event(new Registered($localUser));
            $social->load('ownable');
        } else {
            auth()->login($social->ownable, true);
            $social->update([
                'email' => $user->getEmail(),
                'expires_at' => now()->addSecond($user->expiresIn),
            ]);
        }

        activity()
            ->performedOn($social)
            ->causedBy($social->ownable)
            ->log('logged in');

        return redirect('/dashboard');
    }
}
