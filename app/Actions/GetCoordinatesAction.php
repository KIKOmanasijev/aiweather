<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;

class GetCoordinatesAction
{
    public static function handle(string $place): array
    {
        try {
            $response = Http::get('https://geocoding-api.open-meteo.com/v1/search', [
                'name' => $place,
                'count' => 1,
                'language' => 'en',
                'format' => 'json',
            ]);

            if ($response->successful()) {
                return [
                    'latitude' => $response->json('results.0.latitude'),
                    'longitude' => $response->json('results.0.longitude'),
                ];
            }
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to fetch coordinates data: '.$e->getMessage(),
            ];
        }

        return [
            'error' => 'Failed to fetch coordinates data.'
        ];
    }
}
