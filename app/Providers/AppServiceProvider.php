<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use App\Repositories\KeyRepositoryContract;
use App\Repositories\KeyRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
