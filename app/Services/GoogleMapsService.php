<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Google Maps Platform integration.
 *
 * Primary route geometry: Routes API `computeRoutes`.
 * If that fails and fallbacks are enabled: OSRM (OpenStreetMap roads, Google-compatible polyline), then straight-line Haversine.
 *
 * @see https://developers.google.com/maps/documentation/routes/reference/rest/v2/TopLevel/computeRoutes
 */
class GoogleMapsService
{
    /** POST https://routes.googleapis.com/directions/v2:computeRoutes */
    private const ROUTES_COMPUTE_URL = 'https://routes.googleapis.com/directions/v2:computeRoutes';

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
            'components' => 'country:lb',
            'locationbias' => $locationBias,
            'types' => 'geocode',
        ]);

        $payload = $response->json();

        if (($payload['status'] ?? null) !== 'OK') {
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
     * Distance & duration for one origin–destination pair (legacy Distance Matrix shape).
     * Implemented via Routes API computeRoutes (no polyline) instead of the Distance Matrix web service.
     *
     * @return array<string, mixed>
     */
    public function distanceMatrix(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $mode = 'driving',
    ): array {
        try {
            $route = $this->computeRoutes(
                $originLat,
                $originLng,
                $destLat,
                $destLng,
                $mode,
                includePolyline: false,
            );

            return [
                'distance_km' => $route['distance_km'],
                'distance_miles' => is_numeric($route['distance_km']) ? ((float) $route['distance_km'] * 0.621371) : null,
                'duration_minutes' => $route['duration_minutes'],
                'distance_meters' => $route['distance_meters'],
                'duration_seconds' => $route['duration_seconds'],
                'distance_text' => $route['distance_text'],
                'duration_text' => $route['duration_text'],
            ];
        } catch (\Throwable) {
            if (! config('transport.routes_fallback.enabled', true)) {
                return [
                    'distance_km' => null,
                    'distance_miles' => null,
                    'duration_minutes' => null,
                    'distance_meters' => null,
                    'duration_seconds' => null,
                    'distance_text' => null,
                    'duration_text' => null,
                ];
            }

            if (config('transport.osrm.enabled', true)) {
                try {
                    $osrm = $this->osrmRoute($originLat, $originLng, $destLat, $destLng, $mode, includePolyline: false);

                    return [
                        'distance_km' => $osrm['distance_km'],
                        'distance_miles' => is_numeric($osrm['distance_km']) ? ((float) $osrm['distance_km'] * 0.621371) : null,
                        'duration_minutes' => $osrm['duration_minutes'],
                        'distance_meters' => $osrm['distance_meters'],
                        'duration_seconds' => $osrm['duration_seconds'],
                        'distance_text' => $osrm['distance_text'],
                        'duration_text' => $osrm['duration_text'],
                    ];
                } catch (\Throwable) {
                    /* haversine below */
                }
            }

            $fallback = $this->straightLineRouteEstimate($originLat, $originLng, $destLat, $destLng, includePolyline: false);

            return [
                'distance_km' => $fallback['distance_km'],
                'distance_miles' => is_numeric($fallback['distance_km']) ? ((float) $fallback['distance_km'] * 0.621371) : null,
                'duration_minutes' => $fallback['duration_minutes'],
                'distance_meters' => $fallback['distance_meters'],
                'duration_seconds' => $fallback['duration_seconds'],
                'distance_text' => $fallback['distance_text'],
                'duration_text' => $fallback['duration_text'],
            ];
        }
    }

    /**
     * Driving / walking directions with encoded polyline (Routes API computeRoutes).
     *
     * @return array<string, mixed>
     */
    public function directions(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $mode = 'driving',
    ): array {
        try {
            return $this->computeRoutes(
                $originLat,
                $originLng,
                $destLat,
                $destLng,
                $mode,
                includePolyline: true,
            );
        } catch (\Throwable $e) {
            if (! config('transport.routes_fallback.enabled', true)) {
                throw $e;
            }

            if (config('transport.osrm.enabled', true)) {
                try {
                    return $this->osrmRoute($originLat, $originLng, $destLat, $destLng, $mode, includePolyline: true);
                } catch (\Throwable) {
                    /* straight line below */
                }
            }

            return $this->straightLineRouteEstimate($originLat, $originLng, $destLat, $destLng, includePolyline: true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function computeRoutes(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $mode,
        bool $includePolyline,
    ): array {
        $key = $this->key();
        if ($key === '') {
            throw new \RuntimeException('Google Maps API key is not configured.');
        }

        $mode = in_array($mode, ['driving', 'walking', 'bicycling', 'transit'], true) ? $mode : 'driving';

        $travelMode = match ($mode) {
            'walking' => 'WALK',
            'bicycling' => 'BICYCLE',
            'transit' => 'TRANSIT',
            default => 'DRIVE',
        };

        $body = [
            'origin' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $originLat,
                        'longitude' => $originLng,
                    ],
                ],
            ],
            'destination' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $destLat,
                        'longitude' => $destLng,
                    ],
                ],
            ],
            'travelMode' => $travelMode,
            'languageCode' => 'en',
            'units' => 'METRIC',
        ];

        if ($travelMode === 'DRIVE') {
            $body['routingPreference'] = 'TRAFFIC_AWARE';
        }

        $fieldMask = $includePolyline
            ? 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.viewport'
            : 'routes.duration,routes.distanceMeters';

        $response = Http::timeout(20)
            ->withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => $fieldMask,
                'Content-Type' => 'application/json',
            ])
            ->post(self::ROUTES_COMPUTE_URL, $body);

        if (! $response->successful()) {
            $json = $response->json();
            $msg = $json['error']['message']
                ?? $json['error']['status']
                ?? $response->body();
            throw new \RuntimeException('Google Routes API failed: '.$msg);
        }

        $payload = $response->json();
        $route = $payload['routes'][0] ?? null;
        if (! $route) {
            throw new \RuntimeException('No route found.');
        }

        return $this->normalizeRoutesApiRoute($route, $includePolyline);
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    protected function normalizeRoutesApiRoute(array $route, bool $includePolyline): array
    {
        $distanceRaw = $route['distanceMeters'] ?? null;
        if (is_string($distanceRaw) && is_numeric($distanceRaw)) {
            $distanceMeters = (float) $distanceRaw;
        } elseif (is_numeric($distanceRaw)) {
            $distanceMeters = (float) $distanceRaw;
        } else {
            $distanceMeters = null;
        }

        $durationSeconds = $this->parseDurationSeconds($route['duration'] ?? null);

        $distanceKm = is_numeric($distanceMeters) ? $distanceMeters / 1000.0 : null;
        $durationMinutes = is_numeric($durationSeconds) ? ((float) $durationSeconds / 60.0) : null;

        $encodedPolyline = null;
        if ($includePolyline) {
            $encodedPolyline = $route['polyline']['encodedPolyline'] ?? null;
        }

        $bounds = null;
        $viewport = $route['viewport'] ?? null;
        if (is_array($viewport) && isset($viewport['high'], $viewport['low'])) {
            $bounds = [
                'northeast' => [
                    'lat' => (float) ($viewport['high']['latitude'] ?? 0),
                    'lng' => (float) ($viewport['high']['longitude'] ?? 0),
                ],
                'southwest' => [
                    'lat' => (float) ($viewport['low']['latitude'] ?? 0),
                    'lng' => (float) ($viewport['low']['longitude'] ?? 0),
                ],
            ];
        }

        return [
            'distance_km' => round((float) $distanceKm, 3),
            'duration_minutes' => round((float) $durationMinutes, 1),
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'distance_text' => is_numeric($distanceKm) ? round((float) $distanceKm, 2).' km' : null,
            'duration_text' => is_numeric($durationMinutes) ? (string) (int) round((float) $durationMinutes).' min' : null,
            'encoded_polyline' => $encodedPolyline,
            'bounds' => $bounds,
            'start_address' => null,
            'end_address' => null,
            'google_response_status' => 'OK',
            'approximate_route' => false,
            'route_source' => 'google_routes',
        ];
    }

    /**
     * Haversine distance + ETA from assumed driving speed; optional Google-encoded straight-line polyline (two points).
     *
     * @return array<string, mixed>
     */
    protected function straightLineRouteEstimate(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        bool $includePolyline,
    ): array {
        $km = self::haversineKm($originLat, $originLng, $destLat, $destLng);
        $speed = max(5.0, (float) config('transport.routes_fallback.assumed_speed_kmh', 28));
        $hours = $km / $speed;
        $durationSeconds = max(120, (int) round($hours * 3600));
        $distanceMeters = round($km * 1000, 1);
        $durationMinutes = $durationSeconds / 60.0;

        $encodedPolyline = null;
        if ($includePolyline) {
            $encodedPolyline = self::encodePolylineTwoPoints($originLat, $originLng, $destLat, $destLng);
        }

        $bounds = [
            'southwest' => [
                'lat' => min($originLat, $destLat),
                'lng' => min($originLng, $destLng),
            ],
            'northeast' => [
                'lat' => max($originLat, $destLat),
                'lng' => max($originLng, $destLng),
            ],
        ];

        return [
            'distance_km' => round($km, 3),
            'duration_minutes' => round($durationMinutes, 1),
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'distance_text' => round($km, 2).' km',
            'duration_text' => (string) (int) round($durationMinutes).' min',
            'encoded_polyline' => $encodedPolyline,
            'bounds' => $bounds,
            'start_address' => null,
            'end_address' => null,
            'google_response_status' => 'FALLBACK_STRAIGHT_LINE',
            'approximate_route' => true,
            'route_source' => 'straight_line_estimate',
        ];
    }

    /**
     * OSRM routing (OpenStreetMap roads). Encoded polyline matches Google's precision-5 format for Maps JS decodePath.
     *
     * @return array<string, mixed>
     */
    protected function osrmRoute(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $mode,
        bool $includePolyline,
    ): array {
        $normalizedMode = in_array($mode, ['driving', 'walking', 'bicycling', 'transit'], true) ? $mode : 'driving';

        $profile = match ($normalizedMode) {
            'walking' => 'foot',
            'bicycling' => 'bike',
            'transit' => 'driving',
            default => 'driving',
        };

        $base = (string) config('transport.osrm.base_url', 'https://router.project-osrm.org/route/v1');
        $base = rtrim($base, '/');
        $coords = sprintf('%s,%s;%s,%s', $originLng, $originLat, $destLng, $destLat);
        $url = "{$base}/{$profile}/{$coords}";

        $query = $includePolyline
            ? ['steps' => 'false', 'overview' => 'full', 'geometries' => 'polyline']
            : ['steps' => 'false', 'overview' => 'false'];

        $response = Http::timeout(18)->acceptJson()->get($url, $query);

        if (! $response->successful()) {
            throw new \RuntimeException('OSRM HTTP '.$response->status());
        }

        $payload = $response->json();
        if (($payload['code'] ?? '') !== 'Ok') {
            throw new \RuntimeException('OSRM: '.($payload['message'] ?? ($payload['code'] ?? 'error')));
        }

        $r = $payload['routes'][0] ?? null;
        if (! is_array($r)) {
            throw new \RuntimeException('OSRM returned no route.');
        }

        $distanceMeters = isset($r['distance']) ? (float) $r['distance'] : null;
        $durationSeconds = isset($r['duration']) ? (int) round((float) $r['duration']) : null;

        if (! is_numeric($distanceMeters) || $distanceMeters <= 0 || $durationSeconds === null || $durationSeconds <= 0) {
            throw new \RuntimeException('OSRM returned invalid distance/duration.');
        }

        $distanceKm = $distanceMeters / 1000.0;
        $durationMinutes = $durationSeconds / 60.0;

        $encodedPolyline = null;
        if ($includePolyline && ! empty($r['geometry']) && is_string($r['geometry'])) {
            $encodedPolyline = $r['geometry'];
        }

        $bounds = null;
        $bbox = $r['bbox'] ?? null;
        if (is_array($bbox) && count($bbox) >= 4) {
            $bounds = [
                'southwest' => ['lat' => (float) $bbox[1], 'lng' => (float) $bbox[0]],
                'northeast' => ['lat' => (float) $bbox[3], 'lng' => (float) $bbox[2]],
            ];
        } else {
            $bounds = [
                'southwest' => [
                    'lat' => min($originLat, $destLat),
                    'lng' => min($originLng, $destLng),
                ],
                'northeast' => [
                    'lat' => max($originLat, $destLat),
                    'lng' => max($originLng, $destLng),
                ],
            ];
        }

        return [
            'distance_km' => round($distanceKm, 3),
            'duration_minutes' => round($durationMinutes, 1),
            'distance_meters' => round($distanceMeters, 1),
            'duration_seconds' => $durationSeconds,
            'distance_text' => round($distanceKm, 2).' km',
            'duration_text' => (string) (int) round($durationMinutes).' min',
            'encoded_polyline' => $encodedPolyline,
            'bounds' => $bounds,
            'start_address' => null,
            'end_address' => null,
            'google_response_status' => 'OK',
            'approximate_route' => false,
            'route_source' => 'osrm',
        ];
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * asin(min(1.0, sqrt($a)));
    }

    /** Google Encoded Polyline Algorithm Format for exactly two LatLng points. */
    private static function encodePolylineTwoPoints(float $lat1, float $lng1, float $lat2, float $lng2): string
    {
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;
        foreach ([[$lat1, $lng1], [$lat2, $lng2]] as [$lat, $lng]) {
            $latE5 = (int) round($lat * 1e5);
            $lngE5 = (int) round($lng * 1e5);
            $encoded .= self::encodeSignedNumber($latE5 - $prevLat);
            $encoded .= self::encodeSignedNumber($lngE5 - $prevLng);
            $prevLat = $latE5;
            $prevLng = $lngE5;
        }

        return $encoded;
    }

    private static function encodeSignedNumber(int $num): string
    {
        $sgnNum = $num < 0 ? (~($num << 1)) : ($num << 1);
        $encoded = '';
        while ($sgnNum >= 0x20) {
            $encoded .= chr((int) ((0x20 | ($sgnNum & 0x1f)) + 63));
            $sgnNum >>= 5;
        }
        $encoded .= chr((int) ($sgnNum + 63));

        return $encoded;
    }

    protected function parseDurationSeconds(mixed $duration): ?int
    {
        if ($duration === null) {
            return null;
        }
        if (is_int($duration)) {
            return $duration;
        }
        if (is_float($duration)) {
            return (int) $duration;
        }
        if (is_string($duration)) {
            if (preg_match('/^(\d+(?:\.\d+)?)s$/', $duration, $m)) {
                return (int) floor((float) $m[1]);
            }
            if (is_numeric($duration)) {
                return (int) $duration;
            }
        }
        if (is_array($duration) && isset($duration['seconds'])) {
            $s = $duration['seconds'];
            if (is_numeric($s)) {
                return (int) (is_string($s) ? (float) $s : $s);
            }
        }

        return null;
    }
}
