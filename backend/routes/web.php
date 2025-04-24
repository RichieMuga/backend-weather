<?php

use App\Http\Controllers\Api\WeatherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::middleware('api')->group(function () {
    // Weather by city name
    Route::get('/weather', [WeatherController::class, 'getWeather']);

    // City search endpoint
    Route::get('/cities/search', [WeatherController::class, 'searchCities']);

    // Debug endpoints (remove in production)
    Route::prefix('debug')->group(function () {
        Route::get('/config', function () {
            return response()->json([
                'api_key_loaded' => ! empty(config('services.openweather.api_key')),
                'api_url' => config('services.openweather.api_url'),
                'geo_url' => config('services.openweather.geo_url'),
            ]);
        });

        Route::get('/direct-api-test', function (Request $request) {
            $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => $request->input('city', 'London'),
                'appid' => config('services.openweather.api_key'),
                'units' => 'metric',
            ]);

            return response()->json([
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        });
    });
});
