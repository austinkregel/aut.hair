<?php

namespace App\Http\Controllers\Auth\Team;

use App\Http\Controllers\Controller;
use App\Models\Social;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class CallbackController extends Controller
{
    public function __invoke($provider, Request $request)
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

        $social = Social::with('ownable')->firstWhere([
            'provider' => $provider,
            'provider_id' => $user->getId(),
        ]);

        // Don't create a new user. Link the user to their existing account.
        $localUser = auth()->user() ?? $social->ownable;

        if (empty($localUser)) {
            return redirect('/login?message='.urlencode('You need to register first.'));
        }

        if (empty($social) || ! $localUser->is($social?->ownable)) {
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
            $social->load('ownable');
        } else {
            auth()->login($social->ownable, true);
            $social->update([
                'email' => $user->getEmail(),
                'expires_at' => now()->addSeconds($user->expiresIn),
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
