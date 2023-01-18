<?php

use App\Models\Social;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/.well-known/openid-configuration', function () {
    return response()->json([
        'issuer' => route('issuer'),
        'authorization_endpoint' => route('passport.authorizations.authorize', []),
        'token_endpoint' => route('passport.token', []),
        'userinfo_endpoint' => route('oidc.userinfo', []),
        'jwks_uri' => route('oidc.jwks', []),
        'revocation_endpoint' => route('passport.authorizations.deny'),
        // 'service_documentation' => route('docs'),
        'response_types_supported' => ['code', 'token', 'id_token', 'code token', 'id_token token'],
        'scopes_supported' => ['openid', 'profile', 'email','profile', 'email', 'name'],
        'claims_supported' => ['sub', 'iss', 'roles', 'acr', 'picture', 'profile'],
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'private_key_jwt'],
        'ui_locales_supported' => ['en-US'],
//        'device_authorization_endpoint' => 'https://auth.auth0.com/oauth/device/code',
//        'mfa_challenge_endpoint' => 'https://auth.auth0.com/mfa/challenge',
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->get('/api/social-accounts', function (Request $request) {
    return $request->user()->socials;
});

Route::get('/login/{provider}', function ($provider) {
    abort_if(!in_array($provider, ['google', 'github', 'synology']), 404);

    return Socialite::driver($provider)->redirect();
});

Route::middleware('web')->get('/callback/{provider}', function ($provider) {
    abort_if(!in_array($provider, ['google', 'github', 'synology']), 404);

    if ($provider === 'synology' && empty(request()->get('access_token', null))) {
        return view('synology');
    }

    try {
        $user = Socialite::driver($provider)->user();
    } catch (\Exception $e) {
        return redirect('/login?message='.urlencode($e->getMessage()));
    }

    $social = Social::with('user')->firstWhere([
        'provider' => $provider,
        'provider_id' => $user->getId(),
    ]);

    // Don't create a new user. Link the user to their existing account.
    $localUser = auth()->user() ?? $social->user ?? User::where([
        'email' => $user->getEmail(),
        'name' => $user->getName(),
        'password' => 'unused',
    ])->first();

    if (empty($localUser)) {
        return redirect('/login?message='.urlencode('You need to register first.'));
    }

    if (empty($social) || !$localUser->is($social?->user)) {
        auth()->login($localUser, true);

        $social = Social::create([
            'user_id' => $localUser->id,
            'email' => $user->getEmail(),
            'provider' => $provider,
            'provider_id' => $user->getId(),
            'expires_at' => now()->addSecond($user->expiresIn),
        ]);

        event(new Registered($localUser));
        $social->load('user');
    } else {
        auth()->login($social->user, true);
        $social->update([
            'email' => $user->getEmail(),
            'expires_at' => now()->addSecond($user->expiresIn),
        ]);
    }

    activity()
        ->performedOn($social)
        ->causedBy($social->user)
        ->log('logged in');

    return redirect('/dashboard');
});

Route::get('/', function () {
    return Inertia::location('/login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'currentTeam' => request()->user()->currentTeam,
            'activityItems' => QueryBuilder::for(Activity::class)
                ->allowedFilters(['description', 'subject_type', 'subject_id'])
                ->allowedIncludes(['causer', 'subject'])
                ->allowedSorts(['id', 'name' ,'causer_id', 'subject_id'])
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->where('causer_id', auth()->id())
                        ->where('causer_type', User::class);
                    })
                    ->orWhere(function ($q) {
                        $q->where('causer_id', auth()->user()?->currentTeam?->id)
                            ->where('causer_type', \App\Models\Team::class);
                    });
                })
                ->with('causer', 'subject')
                ->defaultSort('-id')
                ->paginate()
                ->appends(request()->query())
        ]);
    })->name('dashboard');
});
