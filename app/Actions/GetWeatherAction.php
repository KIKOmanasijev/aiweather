<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;

class GetWeatherAction
{
    public static function handle(string $latitude, string $longitude): array
    {
        try {
            $response = Http::get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => [
                    'temperature_2m',
                    'relative_humidity_2m',
                    'apparent_temperature',
                    'precipitation',
                    'wind_speed_10m',
                    'wind_direction_10m',
                ],
                'timezone' => 'auto',
                'forecast_days' => 1,
            ]);

            if ($response->successful()) {
                $values = $response->json('current');
                $units = $response->json('current_units');

                return [
                    'temperature' => $values['temperature_2m'].$units['temperature_2m'],
                    'feels_like' => $values['apparent_temperature'].$units['apparent_temperature'],
                    'humidity' => $values['relative_humidity_2m'].$units['relative_humidity_2m'],
                    'precipitation' => $values['precipitation'].$units['precipitation'],
                    'wind_speed' => $values['wind_speed_10m'].$units['wind_speed_10m'],
                    'wind_direction' => $values['wind_direction_10m'].$units['wind_direction_10m'],
                ];
            }
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to fetch weather data: '.$e->getMessage(),
            ];
        }

        return [
            'error' => 'Unable to fetch weather data',
        ];
    }
}
