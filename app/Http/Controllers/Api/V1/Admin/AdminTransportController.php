<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryBooking;
use App\Models\TaxiBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminTransportController extends Controller
{
    private const STATUS_VALUES = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];

    public function taxiBookings(Request $request): JsonResponse
    {
        $paginator = TaxiBooking::query()
            ->with('customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginatedJson($paginator, hideRouteData: true);
    }

    public function deliveryBookings(Request $request): JsonResponse
    {
        $paginator = DeliveryBooking::query()
            ->with('customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginatedJson($paginator, hideRouteData: true);
    }

    public function updateTaxiStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', self::STATUS_VALUES)],
        ]);

        $booking = TaxiBooking::query()->findOrFail($id);
        $booking->update(['status' => $data['status']]);

        return response()->json(['data' => $booking->fresh('customer')]);
    }

    public function updateDeliveryStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', self::STATUS_VALUES)],
        ]);

        $booking = DeliveryBooking::query()->findOrFail($id);
        $booking->update(['status' => $data['status']]);

        return response()->json(['data' => $booking->fresh('customer')]);
    }

    private function paginatedJson(LengthAwarePaginator $paginator, bool $hideRouteData = false): JsonResponse
    {
        if ($hideRouteData) {
            $paginator->getCollection()->transform(function ($item) {
                $item->makeHidden(['route_data']);

                return $item;
            });
        }

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
