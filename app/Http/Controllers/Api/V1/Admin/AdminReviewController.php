<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Review::query()
            ->with(['client', 'provider.user'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ReviewResource::collection($items)->response();
    }
}
