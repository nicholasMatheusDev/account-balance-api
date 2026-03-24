<?php

namespace App\Providers;

use App\Repositories\AccountRepositoryInterface;
use App\Repositories\JsonFileAccountRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccountRepositoryInterface::class, JsonFileAccountRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
