<?php

namespace App\Providers;

use App\Repositories\KeyRepository;
use App\Repositories\KeyRepositoryContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::ignoreRoutes();
        $this->app->bind(KeyRepositoryContract::class, KeyRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewWebSocketsDashboard', function ($user = null) {
            return in_array($user->email, config('auth.admin_emails'));
        });
    }
}
