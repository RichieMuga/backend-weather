<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WeatherService;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __construct(
        protected WeatherService $weatherService
    ) {}

    /**
     * @queryParam city required City name (e.g. "London")
     */
    public function getWeather(Request $request)
    {
        $request->validate([
            'city' => 'required|string|max:100',
        ]);

        $data = $this->weatherService->getWeatherData($request->query('city'));

        return $data
            ? response()->json($data)
            : response()->json([
                'error' => 'Weather data unavailable',
                'docs' => 'https://openweathermap.org/api',
            ], 502);
    }

    /**
     * @queryParam query required Search term (min 2 chars)
     */
    public function searchCities(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);

        return response()->json(
            $this->weatherService->searchCities($request->query('query'))
        );
    }
}
