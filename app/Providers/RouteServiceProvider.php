<?php

namespace App\Providers;

use App\Http\Middleware\OidcTokenBlacklistMiddleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Forward-auth verify endpoint: public + unauthenticated, so cap per client
        // IP. Configurable via config/forward-auth.php (FORWARD_AUTH_THROTTLE).
        RateLimiter::for('forward-auth', function (Request $request) {
            return Limit::perMinute((int) config('forward-auth.throttle', 30))->by($request->ip());
        });

        $this->routes(function () {
            Route::prefix('api')
                ->middleware(OidcTokenBlacklistMiddleware::class)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::group([
                'as' => 'passport.',
                'namespace' => 'Laravel\Passport\Http\Controllers',
            ], function () {
                Route::prefix('oauth')
                    ->group(base_path('routes/passport.php'));
            });

            // GAIA-compat (openFyde / ChromeOS): root-relative endpoints, no
            // prefix (Chromium calls e.g. /oauth2/v4/token, /oauth2/v1/userinfo).
            // Per-route middleware is set in the file.
            Route::as('gaia.')
                ->group(base_path('routes/gaia.php'));

            // Forward-auth (Authentik-outpost compatible): root-relative
            // /outpost.goauthentik.io/auth/nginx a reverse proxy hits per request.
            // Per-route middleware is set in the file.
            Route::as('forward-auth.')
                ->group(base_path('routes/forward-auth.php'));
        });
    }
}
