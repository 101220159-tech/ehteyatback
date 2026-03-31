<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $paginator = $this->search->searchProviders($filters, $request->integer('per_page', 15));

        return ProviderResource::collection($paginator)->response();
    }
}
