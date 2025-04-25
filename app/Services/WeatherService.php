<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected $apiKey;

    protected $apiUrl;

    protected $geoUrl;

    protected $cacheTime;

    public function __construct()
    {
        $this->apiKey = config('services.openweather.api_key');
        $this->apiUrl = config('services.openweather.api_url');
        $this->geoUrl = config('services.openweather.geo_url');
        $this->cacheTime = config('services.openweather.cache.weather');
    }

    public function getWeatherData(string $city): ?array
    {
        try {
            // Add detailed logging
            Log::info("Fetching weather data for city: {$city}");

            // Cache the results
            $cacheKey = 'weather_' . md5($city);

            return Cache::remember($cacheKey, $this->cacheTime, function () use ($city) {
                return $this->fetchWeatherData($city);
            });
        } catch (\Exception $e) {
            Log::error('Weather service exception: ' . $e->getMessage(), [
                'city' => $city,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    protected function fetchWeatherData(string $city): ?array
    {
        // 1. Get coordinates
        $geoResponse = Http::get("{$this->geoUrl}/direct", [
            'q' => $city,
            'limit' => 1,
            'appid' => $this->apiKey,
        ]);

        if ($geoResponse->failed()) {
            Log::error('Geo API failed', [
                'status' => $geoResponse->status(),
                'body' => $geoResponse->body(),
            ]);
            throw new \Exception('Geo API failed: ' . $geoResponse->status());
        }

        $location = $geoResponse->json()[0] ?? null;
        if (! $location) {
            Log::warning("Location not found for city: {$city}");
            throw new \Exception('Location not found');
        }

        // 2. Get current weather (using the free API)
        $weatherResponse = Http::get("{$this->apiUrl}/weather", [
            'lat' => $location['lat'],
            'lon' => $location['lon'],
            'appid' => $this->apiKey,
            'units' => 'metric',
        ]);

        if ($weatherResponse->failed()) {
            Log::error('Weather API failed', [
                'status' => $weatherResponse->status(),
                'body' => $weatherResponse->body(),
            ]);
            throw new \Exception('Weather API failed: ' . $weatherResponse->status());
        }

        // 3. Get forecast data (using the free 5-day forecast API)
        $forecastResponse = Http::get("{$this->apiUrl}/forecast", [
            'lat' => $location['lat'],
            'lon' => $location['lon'],
            'appid' => $this->apiKey,
            'units' => 'metric',
        ]);

        if ($forecastResponse->failed()) {
            Log::error('Forecast API failed', [
                'status' => $forecastResponse->status(),
                'body' => $forecastResponse->body(),
            ]);
            throw new \Exception('Forecast API failed: ' . $forecastResponse->status());
        }

        return $this->formatWeatherData(
            $weatherResponse->json(),
            $forecastResponse->json(),
            $location
        );
    }

    protected function formatWeatherData(array $weatherData, array $forecastData, array $location): array
    {
        // Extract the daily forecast data (for 3 days)
        $dailyForecasts = [];
        $dates = [];

        // Process the forecast data to get unique days
        foreach ($forecastData['list'] as $forecast) {
            $date = date('Y-m-d', $forecast['dt']);

            // Skip if we already have this date or have enough days
            if (in_array($date, $dates) || count($dates) >= 3) {
                continue;
            }

            $dates[] = $date;
            $dailyForecasts[] = [
                'date' => $date,
                'temp' => [
                    'max' => $forecast['main']['temp_max'],
                    'min' => $forecast['main']['temp_min'],
                ],
                'condition' => [
                    'text' => $forecast['weather'][0]['description'],
                    'icon' => $this->mapWeatherCondition($forecast['weather'][0]['main']),
                ],
            ];
        }

        return [
            'location' => [
                'name' => $location['name'],
                'country' => $location['country'] ?? '',
                'lat' => $location['lat'],
                'lon' => $location['lon'],
            ],
            'current' => [
                'temp_c' => $weatherData['main']['temp'],
                'temp_f' => round($weatherData['main']['temp'] * 9 / 5 + 32, 1),
                'condition' => [
                    'text' => $weatherData['weather'][0]['description'],
                    'icon' => $this->mapWeatherCondition($weatherData['weather'][0]['main']),
                ],
                'humidity' => $weatherData['main']['humidity'],
                'wind_kph' => round($weatherData['wind']['speed'] * 3.6, 1),
                'feels_like' => $weatherData['main']['feels_like'],
                'pressure' => $weatherData['main']['pressure'],
            ],
            'forecast' => [
                'daily' => $dailyForecasts,
            ],
        ];
    }

    public function mapWeatherCondition(string $condition): string
    {
        $condition = strtolower($condition);

        $map = [
            'clear' => 'sunny',
            'clouds' => 'cloudy',
            'rain' => 'rainy',
            'snow' => 'snowy',
            'thunderstorm' => 'thunderstorm',
            'drizzle' => 'drizzle',
            'mist' => 'foggy',
            'smoke' => 'foggy',
            'haze' => 'foggy',
            'fog' => 'foggy',
        ];

        return $map[$condition] ?? 'sunny';
    }

    public function searchCities(string $query): array
    {
        $cacheKey = 'city_search_' . md5($query);

        return Cache::remember($cacheKey, config('services.openweather.cache.search'), function () use ($query) {
            $response = Http::get("{$this->geoUrl}/direct", [
                'q' => $query,
                'limit' => 5,
                'appid' => $this->apiKey,
            ]);

            if (! $response->successful()) {
                Log::error('City search API failed', [
                    'query' => $query,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            return array_map(function ($city) {
                return [
                    'name' => $city['name'],
                    'region' => $city['state'] ?? '',
                    'country' => $city['country'],
                    'lat' => $city['lat'],
                    'lon' => $city['lon'],
                ];
            }, $response->json());
        });
    }
}
