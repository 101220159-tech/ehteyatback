<?php

namespace App\Services;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function searchProviders(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $lat = isset($filters['latitude']) ? (float) $filters['latitude'] : null;
        $lng = isset($filters['longitude']) ? (float) $filters['longitude'] : null;
        $radiusKm = isset($filters['radius_km']) ? (float) $filters['radius_km'] : null;
        $keyword = isset($filters['keyword']) ? trim((string) $filters['keyword']) : null;

        $query = Provider::query()
            ->with(['user:id,name,email,phone,latitude,longitude'])
            ->with(['services.category'])
            ->withCount('reviews');

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
            $categoryId = (int) $filters['category_id'];
            $query->whereHas('services', function (Builder $q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        if (! empty($filters['service_id'])) {
            $serviceId = (int) $filters['service_id'];
            $query->whereHas('services', fn (Builder $q) => $q->where('services.id', $serviceId));
        }

        if (! empty($filters['min_rating'])) {
            $query->where('rating_avg', '>=', (float) $filters['min_rating']);
        }

        $driver = Provider::query()->getConnection()->getDriverName();

        if ($lat !== null && $lng !== null && $driver === 'mysql') {
            $query->join('users', 'users.id', '=', 'providers.user_id')
                ->whereNotNull('users.latitude')
                ->whereNotNull('users.longitude')
                ->groupBy('providers.id');
            $haversine = '(6371 * acos(least(1, greatest(-1, cos(radians(?)) * cos(radians(users.latitude)) * cos(radians(users.longitude) - radians(?)) + sin(radians(?)) * sin(radians(users.latitude))))))';
            $query->selectRaw("providers.*, {$haversine} AS distance_km", [$lat, $lng, $lat]);
            if ($radiusKm) {
                $query->havingRaw('distance_km <= ?', [$radiusKm]);
            }
        } else {
            $query->select('providers.*');
        }

        $sort = $filters['sort'] ?? 'rating';
        if ($sort === 'distance' && $lat !== null && $lng !== null && $driver === 'mysql') {
            $query->orderBy('distance_km');
        } else {
            $query->orderByDesc('rating_avg')->orderByDesc('reviews_count');
        }

        return $query->paginate($perPage);
    }
}
