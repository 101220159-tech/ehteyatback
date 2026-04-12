<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleMapsService
{
    // Lebanon bounds (north/south/west/east) used to bias results.
    private const LEBANON_BOUNDS = [
        'north' => 34.7,
        'south' => 33.0,
        'west' => 35.0,
        'east' => 36.5,
    ];

    public function __construct(
        protected string $apiKey = '',
    ) {}

    protected function key(): string
    {
        $key = $this->apiKey !== '' ? $this->apiKey : (string) config('services.google_maps.key', '');

        return trim($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function geocode(string $address): array
    {
        $key = $this->key();
        if ($key === '') {
            throw new \RuntimeException('Google Maps API key is not configured.');
        }

        $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'key' => $key,
            'language' => 'en',
            'region' => 'lb',
        ]);

        $payload = $response->json();

        if (($payload['status'] ?? null) !== 'OK') {
            throw new \RuntimeException('Google Geocoding failed: '.($payload['error_message'] ?? ($payload['status'] ?? 'unknown')));
        }

        $result = $payload['results'][0] ?? null;
        if (! $result) {
            throw new \RuntimeException('Google Geocoding returned no results.');
        }

        $location = $result['geometry']['location'] ?? null;
        if (! $location || ! isset($location['lat'], $location['lng'])) {
            throw new \RuntimeException('Google Geocoding returned invalid geometry.');
        }

        return [
            'formatted_address' => $result['formatted_address'] ?? null,
            'place_id' => $result['place_id'] ?? null,
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lng'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function autocomplete(string $input): array
    {
        $key = $this->key();
        if ($key === '') {
            throw new \RuntimeException('Google Maps API key is not configured.');
        }

        $b = self::LEBANON_BOUNDS;
        $locationBias = sprintf(
            'rectangle:%s,%s,%s,%s',
            $b['north'],
            $b['west'],
            $b['south'],
            $b['east'],
        );

        $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input' => $input,
            'key' => $key,
            'language' => 'en',
            // Keep results inside Lebanon.
            'components' => 'country:lb',
            'locationbias' => $locationBias,
            'types' => 'geocode',
        ]);

        $payload = $response->json();

        if (($payload['status'] ?? null) !== 'OK') {
            // ZERO_RESULTS is not an error for our UI.
            if (($payload['status'] ?? null) === 'ZERO_RESULTS') {
                return ['predictions' => []];
            }

            throw new \RuntimeException('Google Places Autocomplete failed: '.($payload['error_message'] ?? ($payload['status'] ?? 'unknown')));
        }

        $predictions = array_map(function ($p) {
            return [
                'place_id' => $p['place_id'] ?? null,
                'description' => $p['description'] ?? null,
                'main_text' => $p['structured_formatting']['main_text'] ?? null,
                'secondary_text' => $p['structured_formatting']['secondary_text'] ?? null,
            ];
        }, $payload['predictions'] ?? []);

        return ['predictions' => $predictions];
    }

    /**
     * @return array<string, mixed>
     */
    public function distanceMatrix(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $mode = 'driving',
    ): array {
        $key = $this->key();
        if ($key === '') {
            throw new \RuntimeException('Google Maps API key is not configured.');
        }

        $mode = in_array($mode, ['driving', 'walking', 'bicycling', 'transit'], true) ? $mode : 'driving';

        $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins' => $originLat.','.$originLng,
            'destinations' => $destLat.','.$destLng,
            'mode' => $mode,
            'units' => 'metric',
            'key' => $key,
            'language' => 'en',
        ]);

        $payload = $response->json();

        if (($payload['status'] ?? null) !== 'OK') {
            throw new \RuntimeException('Google Distance Matrix failed: '.($payload['error_message'] ?? ($payload['status'] ?? 'unknown')));
        }

        $element = $payload['rows'][0]['elements'][0] ?? null;
        if (! $element || ($element['status'] ?? null) !== 'OK') {
            return [
                'distance_km' => null,
                'duration_minutes' => null,
                'distance_meters' => null,
                'duration_seconds' => null,
            ];
        }

        $distanceMeters = $element['distance']['value'] ?? null;
        $durationSeconds = $element['duration']['value'] ?? null;

        $distanceKm = is_numeric($distanceMeters) ? ((float) $distanceMeters / 1000.0) : null;
        $distanceMiles = is_numeric($distanceKm) ? ((float) $distanceKm * 0.621371) : null;
        $durationMinutes = is_numeric($durationSeconds) ? ((float) $durationSeconds / 60.0) : null;

        return [
            'distance_km' => $distanceKm,
            'distance_miles' => $distanceMiles,
            'duration_minutes' => $durationMinutes,
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'distance_text' => $element['distance']['text'] ?? null,
            'duration_text' => $element['duration']['text'] ?? null,
        ];
    }
}

