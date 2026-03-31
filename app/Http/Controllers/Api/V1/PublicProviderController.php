<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Models\Provider;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicProviderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Provider::query()
            ->with(['user:id,name,email', 'services.category'])
            ->withCount('reviews');

        if ($request->filled('category_id')) {
            $query->whereHas('services', fn ($q) => $q->where('category_id', $request->integer('category_id')));
        }

        if ($request->filled('min_rating')) {
            $query->where('rating_avg', '>=', $request->float('min_rating'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('bio', 'like', $term)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $term));
            });
        }

        $query->orderByDesc('rating_avg');

        return ProviderResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(int $id, CacheService $cache): ProviderResource
    {
        $provider = $cache->remember(
            'provider_public_'.$id,
            60,
            fn () => Provider::query()
                ->with(['user:id,name,email', 'services.category', 'projects.images'])
                ->withCount('reviews')
                ->findOrFail($id)
        );

        return new ProviderResource($provider);
    }
}
