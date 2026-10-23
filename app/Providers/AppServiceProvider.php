<?php

namespace App\Providers;

use App\Support\Updater\UpdateRunner;
use App\Support\Updater\Updater;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Updater::class, fn () => new Updater(config('updater')));
        $this->app->bind(UpdateRunner::class, fn ($app) => new UpdateRunner($app->make(Updater::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
