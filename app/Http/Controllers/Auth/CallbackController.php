<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Social;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class CallbackController extends Controller
{
    public function __invoke($provider, Request $request)
    {
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
        $headerLogs = iterator_to_array($request->headers->getIterator());

        unset($headerLogs['cookie']);
        unset($headerLogs['authorization']);
        unset($headerLogs['x-csrf-token']);
        unset($headerLogs['x-xsrf-token']);

        activity()
            ->performedOn($social)
            ->causedBy($social->ownable)
            ->withProperty('ip', $request->ip())
            ->withProperty('headers', $headerLogs)
            ->log('logged in');

        return redirect('/dashboard');
    }
}
