<?php

use App\Models\Social;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
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
Route::get('/webman/sso/synoSSO-1.0.0.js', function () {
    return response(file_get_contents(resource_path('synoSSO-1.0.0.js')), 200, [
        'content-type' => 'application/javascript'
    ]);
});

Route::get('/webman/sso/SSOOauth.cgi', function () {
    return dd(request()->all());
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
        dd($e);
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
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('api/activity-log', function () {
        return QueryBuilder::for(Activity::class)
            ->allowedFilters(['description', 'subject_type', 'subject_id'])
            ->allowedIncludes(['causer', 'subject'])
            ->allowedSorts(['id', 'name' ,'causer_id', 'subject_id'])
            ->where('causer_id', auth()->id())
            ->paginate()
            ->appends(request()->query());
    });
});
