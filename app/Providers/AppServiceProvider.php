<?php

namespace App\Providers;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Contracts\Repositories\ProfileRepositoryInterface;
use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\ProfileServiceInterface;
use App\Repositories\EloquentAuthRepository;
use App\Repositories\EloquentProfileRepository;
use App\Services\AuthService;
use App\Services\ProfileService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthRepositoryInterface::class, EloquentAuthRepository::class);
        $this->app->bind(ProfileRepositoryInterface::class, EloquentProfileRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(ProfileServiceInterface::class, ProfileService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
