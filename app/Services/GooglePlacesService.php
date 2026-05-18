<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GooglePlacesService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.key');
    }

    public function findPlace(string $query, ?float $latitude = null, ?float $longitude = null): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        $params = [
            'input' => $query,
            'inputtype' => 'textquery',
            'fields' => 'place_id,name,formatted_address,geometry',
            'key' => $this->apiKey,
        ];

        if ($latitude !== null && $longitude !== null) {
            $params['locationbias'] = "circle:5000@{$latitude},{$longitude}";
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/place/findplacefromtext/json', $params);

        if ($response->successful() && $response->json('candidates')) {
            return $response->json('candidates')[0];
        }

        return null;
    }

    /**
     * Places Text Search — returns public POIs (not limited to NexVex providers).
     *
     * @return array<int, array{name: string, place_id: string, formatted_address: ?string, rating: ?float, user_ratings_total: int, maps_url: string}>
     */
    public function textSearch(string $query, ?float $latitude = null, ?float $longitude = null, int $limit = 5): array
    {
        if (! $this->apiKey || trim($query) === '') {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $query = trim($query);
        $cacheKey = 'gplaces_text_'.md5($query.'|'.($latitude ?? '').'|'.($longitude ?? '').'|'.$limit);

        return Cache::remember($cacheKey, (int) env('CHATBOT_GOOGLE_SEARCH_CACHE_SECONDS', 3600), function () use ($query, $latitude, $longitude, $limit) {
            $params = [
                'query' => $query,
                'key' => $this->apiKey,
            ];

            if ($latitude !== null && $longitude !== null && is_finite($latitude) && is_finite($longitude)) {
                $params['location'] = $latitude.','.$longitude;
                $params['radius'] = min(50000, max(1000, (int) env('CHATBOT_GOOGLE_SEARCH_RADIUS_M', 25000)));
            }

            $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/textsearch/json', $params);

            if (! $response->successful()) {
                return [];
            }

            $status = $response->json('status');
            if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
                return [];
            }

            $results = $response->json('results') ?? [];
            $out = [];
            foreach ($results as $row) {
                if (count($out) >= $limit) {
                    break;
                }
                $pid = $row['place_id'] ?? null;
                $name = $row['name'] ?? null;
                if (! is_string($pid) || $pid === '' || ! is_string($name) || $name === '') {
                    continue;
                }
                $out[] = [
                    'name' => $name,
                    'place_id' => $pid,
                    'formatted_address' => $row['formatted_address'] ?? null,
                    'rating' => isset($row['rating']) ? (float) $row['rating'] : null,
                    'user_ratings_total' => (int) ($row['user_ratings_total'] ?? 0),
                    'maps_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($name).'&query_place_id='.rawurlencode($pid),
                ];
            }

            return $out;
        });
    }

    /**
     * Nearby Search for emergency POIs (hospitals, pharmacies).
     *
     * @return array<int, array<string, mixed>>
     */
    public function nearbyEmergency(float $latitude, float $longitude, string $type, int $radiusMeters = 8000): array
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('Google Maps API key is not configured (GOOGLE_MAPS_API_KEY).');
        }

        $type = in_array($type, ['hospital', 'pharmacy'], true) ? $type : 'hospital';
        $radiusMeters = max(500, min(50000, $radiusMeters));

        $cacheKey = sprintf(
            'gplaces_nearby_%s_%s_%s_%d',
            round($latitude, 4),
            round($longitude, 4),
            $type,
            $radiusMeters,
        );

        return Cache::remember($cacheKey, 300, function () use ($latitude, $longitude, $type, $radiusMeters) {
            $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
                'location' => $latitude.','.$longitude,
                'radius' => $radiusMeters,
                'type' => $type,
                'key' => $this->apiKey,
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Google Places Nearby Search request failed.');
            }

            $status = $response->json('status');
            if ($status === 'ZERO_RESULTS') {
                return [];
            }
            if ($status !== 'OK') {
                $message = $response->json('error_message') ?? $status;
                throw new \RuntimeException('Google Places Nearby Search: '.$message);
            }

            $results = $response->json('results') ?? [];
            $out = [];

            foreach ($results as $row) {
                $loc = $row['geometry']['location'] ?? null;
                $lat = isset($loc['lat']) ? (float) $loc['lat'] : null;
                $lng = isset($loc['lng']) ? (float) $loc['lng'] : null;
                $placeId = $row['place_id'] ?? null;
                $name = $row['name'] ?? null;

                if (! is_string($placeId) || $placeId === '' || ! is_string($name) || $name === '' || $lat === null || $lng === null) {
                    continue;
                }

                $distanceKm = self::haversineKm($latitude, $longitude, $lat, $lng);
                $hours = $row['opening_hours'] ?? null;
                $openNow = is_array($hours) ? ($hours['open_now'] ?? null) : null;
                $openStatus = $openNow === true ? 'open' : ($openNow === false ? 'closed' : 'unknown');
                $openLabel = $openNow === true ? 'Open now' : ($openNow === false ? 'Closed now' : 'Hours unknown');

                $out[] = [
                    'place_id' => $placeId,
                    'name' => $name,
                    'address' => $row['vicinity'] ?? $row['formatted_address'] ?? null,
                    'lat' => $lat,
                    'lng' => $lng,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'distance_km' => round($distanceKm, 3),
                    'distance_label' => self::formatDistanceKm($distanceKm),
                    'open_status' => $openStatus,
                    'open_label' => $openLabel,
                    'place_type' => $type,
                    'filter_type' => $type,
                    'rating' => isset($row['rating']) ? (float) $row['rating'] : null,
                    'user_ratings_total' => (int) ($row['user_ratings_total'] ?? 0),
                ];
            }

            usort($out, fn ($a, $b) => ($a['distance_km'] ?? 999) <=> ($b['distance_km'] ?? 999));

            return $out;
        });
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * asin(min(1.0, sqrt($a)));
    }

    private static function formatDistanceKm(float $km): string
    {
        if ($km < 1) {
            return (string) (int) round($km * 1000).' m';
        }

        return number_format($km, 1).' km';
    }

    public function getPlaceDetails(string $placeId): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        return Cache::remember("google_place_{$placeId}", 86400, function () use ($placeId) {
            $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'fields' => 'name,rating,user_ratings_total,reviews,formatted_address',
                'key' => $this->apiKey,
            ]);

            if ($response->successful() && $response->json('status') === 'OK') {
                $result = $response->json('result');

                return [
                    'name' => $result['name'] ?? null,
                    'rating' => $result['rating'] ?? null,
                    'total_ratings' => $result['user_ratings_total'] ?? 0,
                    'reviews' => array_slice($result['reviews'] ?? [], 0, 5),
                    'address' => $result['formatted_address'] ?? null,
                ];
            }

            return null;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array<int, string>
     */
    public function extractReviewInsights(array $reviews): array
    {
        $positiveKeywords = [];
        $positiveWords = ['great', 'excellent', 'amazing', 'professional', 'fast', 'clean', 'reliable', 'friendly'];

        foreach ($reviews as $review) {
            $text = strtolower((string) ($review['text'] ?? ''));
            $rating = (int) ($review['rating'] ?? 3);

            if ($rating >= 4) {
                foreach ($positiveWords as $word) {
                    if (str_contains($text, $word)) {
                        $positiveKeywords[$word] = ($positiveKeywords[$word] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($positiveKeywords);

        return array_slice(array_keys($positiveKeywords), 0, 3);
    }
}
