<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Jobs\UpdateProviderRatingJob;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReviewController extends Controller
{
    public function store(ReviewRequest $request): JsonResponse
    {
        $booking = \App\Models\Booking::where('id', $request->input('booking_id'))
            ->where('customer_id', $request->user()->id)
            ->where('status', 'completed')
            ->firstOrFail();

        $review = Review::query()->create([
            'booking_id'  => $booking->id,
            'customer_id' => $request->user()->id,
            'provider_id' => $booking->provider_id,
            'rating'      => $request->integer('rating'),
            'comment'     => $request->input('comment'),
        ]);

        UpdateProviderRatingJob::dispatch($review->provider_id);

        return (new ReviewResource($review->load(['customer', 'provider.user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $id): ReviewResource
    {
        $review = Review::query()
            ->where('customer_id', $request->user()->id)
            ->findOrFail($id);

        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $review->update($data);

        UpdateProviderRatingJob::dispatch($review->provider_id);

        return new ReviewResource($review->fresh()->load(['customer', 'provider.user']));
    }
}
