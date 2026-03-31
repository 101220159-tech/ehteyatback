<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderReviewController extends Controller
{
    protected function provider(Request $request)
    {
        $p = $request->user()->provider;
        abort_if(! $p, 404, 'Provider profile not found.');

        return $p;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $this->provider($request);
        $reviews = Review::query()
            ->where('provider_id', $provider->id)
            ->with('client')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return ReviewResource::collection($reviews)->response();
    }
}
