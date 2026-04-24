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
            ->with(['customer', 'provider.user', 'service', 'earning', 'review'])
            ->when($request->filled('status'),    fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('scheduled_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('scheduled_at', '<=', $request->date_to))
            ->orderByDesc('scheduled_at')
            ->paginate($request->integer('per_page', 20));

        return BookingResource::collection($items)->response();
    }
}
