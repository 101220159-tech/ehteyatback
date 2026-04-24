<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\CategoryResource;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Provider;
use App\Models\Service;
use App\Models\ServiceCategory;

class SearchController extends Controller
{
    public function __construct(
        protected SearchService $search
    ) {}

    public function providers(Request $request): JsonResponse
    {
        $filters = $request->only([
            'latitude', 'longitude', 'radius_km', 'category_id', 'service_id',
            'min_rating', 'city_id', 'sort',
        ]);

        // Keyword/speciality search: frontends may send either `keyword` or `q`.
        $filters['keyword'] = $request->input('keyword', $request->input('q'));

        $paginator = $this->search->searchProviders($filters, $request->integer('per_page', 15));

        return ProviderResource::collection($paginator)->response();
    }

    public function getSearchSuggestions(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        if ($keyword === '') {
            return response()->json([
                'categories' => [],
                'services' => [],
                'providers' => [],
            ]);
        }

        $like = '%'.$keyword.'%';

        $categories = ServiceCategory::query()
            ->where('is_active', true)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(5)
            ->get();

        $services = Service::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->with('category')
            ->orderBy('name')
            ->limit(5)
            ->get();

        $providers = Provider::query()
            ->where(function ($q) use ($like) {
                $q->where('bio', 'like', $like)
                    ->orWhereHas('user', function ($uq) use ($like) {
                        $uq->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    })
                    ->orWhereHas('services', function ($sq) use ($like) {
                        $sq->where('name', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhereHas('category', function ($cq) use ($like) {
                                $cq->where('service_categories.name', 'like', $like);
                            });
                    });
            })
            ->with(['user:id,name,phone,email,avatar_url'])
            ->orderByDesc('rating_avg')
            ->limit(5)
            ->get();

        return response()->json([
            'categories' => CategoryResource::collection($categories)->resolve(),
            'services'   => ServiceResource::collection($services)->resolve(),
            'providers'  => $providers->map(fn (Provider $p) => [
                'id'         => $p->id,
                'name'       => $p->user?->name,
                'bio'        => $p->bio,
                'rating_avg' => $p->rating_avg,
                'latitude'   => $p->latitude,
                'longitude'  => $p->longitude,
            ])->values(),
        ]);
    }

    public function getServiceCategories(Request $request): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return CategoryResource::collection($categories)->response();
    }

    public function getServicesByCategory(string $categoryId): JsonResponse
    {
        $services = Service::query()
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        return ServiceResource::collection($services)->response();
    }
}
