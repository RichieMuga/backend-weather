<?php

namespace App\Providers;

use App\Services\WeatherService;
use Illuminate\Support\ServiceProvider;

class WeatherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WeatherService::class, function ($app) {
            return new WeatherService();
        });
    }

    public function boot(): void
    {
        //
    }
}
