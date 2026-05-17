<?php

namespace App\Services;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Services\ZoneDetectionService;

class SearchService
{
    public function __construct(protected ZoneDetectionService $zoneDetector) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function searchProviders(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $lat      = isset($filters['latitude'])  ? (float) $filters['latitude']  : null;
        $lng      = isset($filters['longitude']) ? (float) $filters['longitude'] : null;
        $radiusKm = isset($filters['radius_km']) ? (float) $filters['radius_km'] : null;
        $keyword  = isset($filters['keyword'])   ? trim((string) $filters['keyword']) : null;

        $query = Provider::query()
            ->with(['user:id,name,email,phone,avatar_url', 'services.category', 'zones'])
            ->withCount('reviews')
            ->where('is_active', true);

        if ($keyword !== null && $keyword !== '') {
            $like = '%'.$keyword.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('providers.bio', 'like', $like)
                    ->orWhereHas('user', function (Builder $uq) use ($like) {
                        $uq->where('users.name', 'like', $like)
                            ->orWhere('users.phone', 'like', $like)
                            ->orWhere('users.email', 'like', $like);
                    })
                    ->orWhereHas('services', function (Builder $sq) use ($like) {
                        $sq->where('services.name', 'like', $like)
                            ->orWhere('services.description', 'like', $like)
                            ->orWhereHas('category', function (Builder $cq) use ($like) {
                                $cq->where('service_categories.name', 'like', $like);
                            });
                    });
            });
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('services', function (Builder $q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if (! empty($filters['service_id'])) {
            $query->whereHas('services', fn (Builder $q) => $q->where('services.id', $filters['service_id']));
        }

        if (! empty($filters['min_rating'])) {
            $query->where('rating_avg', '>=', (float) $filters['min_rating']);
        }

        // If zone_id not given but lat/lng are, auto-detect which zone the customer is in
        if (empty($filters['zone_id']) && $lat !== null && $lng !== null) {
            $filters['zone_id'] = $this->zoneDetector->detectZoneId($lat, $lng);
        }

        // Zone filter: local providers in the customer's zone + all VIP providers (any zone).
        if (! empty($filters['zone_id'])) {
            $zoneId = $filters['zone_id'];
            $query->where(function (Builder $q) use ($zoneId) {
                $q->where('providers.is_vip', true)
                    ->orWhereHas('zones', fn ($zq) => $zq->where('zones.id', $zoneId));
            });
        }

        $driver = Provider::query()->getConnection()->getDriverName();

        // Use providers.latitude / providers.longitude — no JOIN needed, no GROUP BY issue.
        // Embed float values directly (safe — not user strings) to avoid binding conflicts with withCount.
        if ($lat !== null && $lng !== null && $driver === 'mysql') {
            $haversine = "(CASE WHEN providers.latitude IS NOT NULL AND providers.longitude IS NOT NULL
                THEN (6371 * acos(least(1, greatest(-1,
                cos(radians({$lat})) * cos(radians(providers.latitude)) * cos(radians(providers.longitude) - radians({$lng}))
                + sin(radians({$lat})) * sin(radians(providers.latitude))
            )))) ELSE NULL END) AS distance_km";

            // VIP providers may appear without coordinates; others need lat/lng for distance.
            $query->where(function (Builder $q) {
                $q->where('providers.is_vip', true)
                    ->orWhere(function (Builder $q2) {
                        $q2->whereNotNull('providers.latitude')->whereNotNull('providers.longitude');
                    });
            })->addSelect(DB::raw($haversine));

            // Only apply radius cut-off when NO zone is specified.
            // VIP providers are always listed regardless of distance.
            $zoneIsActive = ! empty($filters['zone_id']);
            if ($radiusKm && ! $zoneIsActive) {
                $query->havingRaw("(providers.is_vip = 1 OR distance_km <= {$radiusKm})");
            }
        }

        $sort = $filters['sort'] ?? 'default';
        if ($sort === 'distance' && $lat !== null && $lng !== null && $driver === 'mysql') {
            $query->orderBy('distance_km')
                  ->orderByDesc('providers.is_vip')
                  ->orderByDesc('providers.rating_avg')
                  ->orderByDesc('providers.experience_years');
        } else {
            $query->orderByDesc('providers.is_vip')
                  ->orderByDesc('providers.rating_avg')
                  ->orderByDesc('providers.experience_years');
        }

        return $query->paginate($perPage);
    }
}
