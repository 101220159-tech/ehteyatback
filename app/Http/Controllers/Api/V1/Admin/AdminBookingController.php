<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Booking::query()
            ->with(['client', 'provider.user', 'service'])
            ->orderByDesc('booking_date')
            ->paginate($request->integer('per_page', 20));

        return BookingResource::collection($items)->response();
    }
}
