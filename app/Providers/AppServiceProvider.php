<?php

namespace App\Providers;

use App\Domain\AccountRepositoryInterface;
use App\Domain\IdempotencyStoreInterface;
use App\Infrastructure\JsonFileAccountRepository;
use App\Infrastructure\JsonFileIdempotencyStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccountRepositoryInterface::class, JsonFileAccountRepository::class);
        $this->app->singleton(IdempotencyStoreInterface::class, JsonFileIdempotencyStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
