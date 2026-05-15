<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\DeliveryBooking;
use App\Models\TaxiBooking;
use App\Services\GoogleMapsService;
use App\Services\TransportPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerTransportController extends Controller
{
    public function __construct(
        protected GoogleMapsService $maps,
        protected TransportPricingService $pricing,
    ) {}

    /** GET /customer/transport/route/calculate */
    public function routeCalculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pickup_lat'         => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng'         => ['required', 'numeric', 'between:-180,180'],
            'destination_lat'    => ['required', 'numeric', 'between:-90,90'],
            'destination_lng'    => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $route = $this->maps->directions(
                (float) $data['pickup_lat'],
                (float) $data['pickup_lng'],
                (float) $data['destination_lat'],
                (float) $data['destination_lng'],
                'driving',
            );

            return response()->json([
                'data' => [
                    'distance_km' => $route['distance_km'],
                    'duration_minutes' => $route['duration_minutes'],
                    'duration_text' => $route['duration_text'],
                    'distance_text' => $route['distance_text'],
                    'encoded_polyline' => $route['encoded_polyline'],
                    'bounds' => $route['bounds'],
                    'start_address' => $route['start_address'],
                    'end_address' => $route['end_address'],
                    'approximate_route' => (bool) ($route['approximate_route'] ?? false),
                    'route_source' => $route['route_source'] ?? 'google_routes',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** GET /customer/transport/estimate */
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode'              => ['required', 'in:taxi,delivery'],
            'pickup_lat'       => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng'       => ['required', 'numeric', 'between:-180,180'],
            'destination_lat' => ['required', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required', 'numeric', 'between:-180,180'],
            'passenger_count'  => ['nullable', 'integer', 'min:1', 'max:8'],
            'vehicle_type'     => ['nullable', 'string', 'in:standard,sedan,suv,van,premium'],
            'delivery_vehicle' => ['nullable', 'string', 'in:motorcycle,car,truck'],
            'package_weight'   => ['nullable', 'numeric', 'min:0.01', 'max:5000'],
            'package_quantity'=> ['nullable', 'integer', 'min:1', 'max:999'],
            'fragile'          => ['nullable', 'boolean'],
        ]);

        try {
            $route = $this->maps->directions(
                (float) $data['pickup_lat'],
                (float) $data['pickup_lng'],
                (float) $data['destination_lat'],
                (float) $data['destination_lng'],
                'driving',
            );

            $km       = (float) $route['distance_km'];
            $duration = (float) $route['duration_minutes'];

            if ($data['mode'] === 'taxi') {
                $vehicle = $data['vehicle_type'] ?? 'standard';
                $price   = $this->pricing->estimateTaxi($km, $duration, $vehicle);

                return response()->json([
                    'data' => array_merge([
                        'mode' => 'taxi',
                        'distance_km' => $km,
                        'duration_minutes' => $duration,
                        'encoded_polyline' => $route['encoded_polyline'],
                        'bounds' => $route['bounds'],
                        'approximate_route' => (bool) ($route['approximate_route'] ?? false),
                        'route_source' => $route['route_source'] ?? 'google_routes',
                    ], $price, ['vehicle_type' => $vehicle]),
                ]);
            }

            $veh     = $data['delivery_vehicle'] ?? 'motorcycle';
            $w       = (float) ($data['package_weight'] ?? 10);
            $qty     = (int) ($data['package_quantity'] ?? 1);
            $fragile = (bool) ($data['fragile'] ?? false);

            $estimate = $this->pricing->estimateDelivery($km, $duration, $veh, $w, $qty, $fragile);
            $suggest = $this->pricing->suggestDeliveryVehicle($w);

            return response()->json([
                'data' => array_merge([
                    'mode' => 'delivery',
                    'distance_km' => $km,
                    'duration_minutes' => $duration,
                    'encoded_polyline' => $route['encoded_polyline'],
                    'bounds' => $route['bounds'],
                    'approximate_route' => (bool) ($route['approximate_route'] ?? false),
                    'route_source' => $route['route_source'] ?? 'google_routes',
                    'smart_suggested_vehicle' => $suggest,
                ], $estimate, ['delivery_vehicle' => $veh]),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** POST /customer/transport/taxi/book */
    public function bookTaxi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pickup_location'             => ['required', 'array'],
            'pickup_location.lat'         => ['required', 'numeric', 'between:-90,90'],
            'pickup_location.lng'         => ['required', 'numeric', 'between:-180,180'],
            'pickup_location.address'      => ['nullable', 'string', 'max:500'],
            'destination_location'        => ['required', 'array'],
            'destination_location.lat'    => ['required', 'numeric', 'between:-90,90'],
            'destination_location.lng'    => ['required', 'numeric', 'between:-180,180'],
            'destination_location.address'=> ['nullable', 'string', 'max:500'],
            'passenger_count'             => ['required', 'integer', 'min:1', 'max:8'],
            'vehicle_type'                => ['nullable', 'string', 'in:standard,sedan,suv,van,premium'],
        ]);

        $user = $request->user();

        try {
            $route = $this->maps->directions(
                (float) $data['pickup_location']['lat'],
                (float) $data['pickup_location']['lng'],
                (float) $data['destination_location']['lat'],
                (float) $data['destination_location']['lng'],
                'driving',
            );

            $vehicle = $data['vehicle_type'] ?? 'standard';
            $price   = $this->pricing->estimateTaxi((float) $route['distance_km'], (float) $route['duration_minutes'], $vehicle);

            $routeData = [
                'encoded_polyline' => $route['encoded_polyline'],
                'bounds' => $route['bounds'],
                'distance_text' => $route['distance_text'],
                'duration_text' => $route['duration_text'],
                'approximate_route' => (bool) ($route['approximate_route'] ?? false),
                'route_source' => $route['route_source'] ?? 'google_routes',
            ];

            $booking = DB::transaction(fn () => TaxiBooking::create([
                'customer_id'                 => $user->id,
                'pickup_location'             => $data['pickup_location'],
                'destination_location'        => $data['destination_location'],
                'passenger_count'             => $data['passenger_count'],
                'vehicle_type'                => $vehicle,
                'distance_km'                 => $route['distance_km'],
                'estimated_duration_minutes'  => $route['duration_minutes'],
                'estimated_price'             => $price['estimated_price'],
                'route_data'                  => $routeData,
                'status'                      => 'pending',
            ]));

            return response()->json([
                'message' => 'Taxi booking created.',
                'data'    => $booking,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** POST /customer/transport/delivery/book */
    public function bookDelivery(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pickup_location'          => ['required', 'array'],
            'pickup_location.lat'      => ['required', 'numeric', 'between:-90,90'],
            'pickup_location.lng'      => ['required', 'numeric', 'between:-180,180'],
            'pickup_location.address' => ['nullable', 'string', 'max:500'],
            'dropoff_location'          => ['required', 'array'],
            'dropoff_location.lat'     => ['required', 'numeric', 'between:-90,90'],
            'dropoff_location.lng'     => ['required', 'numeric', 'between:-180,180'],
            'dropoff_location.address' => ['nullable', 'string', 'max:500'],
            'vehicle_type'             => ['required', 'string', 'in:motorcycle,car,truck'],
            'package_weight'           => ['required', 'numeric', 'min:0.01', 'max:5000'],
            'package_quantity'         => ['required', 'integer', 'min:1', 'max:999'],
            'package_type'             => ['nullable', 'string', 'max:120'],
            'fragile'                  => ['boolean'],
        ]);

        $maxBike = $this->pricing->motorcycleMaxKg();
        if ($data['vehicle_type'] === 'motorcycle' && (float) $data['package_weight'] > $maxBike) {
            throw ValidationException::withMessages([
                'package_weight' => ["Weight exceeds motorcycle limit ({$maxBike} kg). Choose car or truck."],
            ]);
        }

        $user = $request->user();

        try {
            $route = $this->maps->directions(
                (float) $data['pickup_location']['lat'],
                (float) $data['pickup_location']['lng'],
                (float) $data['dropoff_location']['lat'],
                (float) $data['dropoff_location']['lng'],
                'driving',
            );

            $estimate = $this->pricing->estimateDelivery(
                (float) $route['distance_km'],
                (float) $route['duration_minutes'],
                $data['vehicle_type'],
                (float) $data['package_weight'],
                (int) $data['package_quantity'],
                (bool) ($data['fragile'] ?? false),
            );

            $routeData = [
                'encoded_polyline' => $route['encoded_polyline'],
                'bounds' => $route['bounds'],
                'distance_text' => $route['distance_text'],
                'duration_text' => $route['duration_text'],
                'approximate_route' => (bool) ($route['approximate_route'] ?? false),
                'route_source' => $route['route_source'] ?? 'google_routes',
            ];

            $booking = DB::transaction(fn () => DeliveryBooking::create([
                'customer_id'                 => $user->id,
                'pickup_location'             => $data['pickup_location'],
                'dropoff_location'            => $data['dropoff_location'],
                'vehicle_type'                => $data['vehicle_type'],
                'package_weight'              => $data['package_weight'],
                'package_quantity'            => $data['package_quantity'],
                'package_type'                => $data['package_type'] ?? null,
                'fragile'                     => (bool) ($data['fragile'] ?? false),
                'distance_km'                 => $route['distance_km'],
                'estimated_duration_minutes'=> $route['duration_minutes'],
                'shipping_price'              => $estimate['shipping_price'],
                'route_data'                  => $routeData,
                'status'                      => 'pending',
            ]));

            return response()->json([
                'message' => 'Delivery booking created.',
                'data'    => $booking,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** GET /customer/transport/live-tracking */
    public function liveTracking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:taxi,delivery'],
            'id'   => ['required', 'uuid'],
        ]);

        $userId = $request->user()->id;

        if ($data['type'] === 'taxi') {
            $b = TaxiBooking::where('customer_id', $userId)->findOrFail($data['id']);
        } else {
            $b = DeliveryBooking::where('customer_id', $userId)->findOrFail($data['id']);
        }

        $pickup = $b->pickup_location ?? [];
        $dest = $data['type'] === 'taxi'
            ? ($b->destination_location ?? [])
            : ($b->dropoff_location ?? []);

        $etaRefresh = null;
        if (! empty($pickup['lat']) && ! empty($pickup['lng']) && ! empty($dest['lat']) && ! empty($dest['lng'])) {
            try {
                $etaRefresh = $this->maps->distanceMatrix(
                    (float) $pickup['lat'],
                    (float) $pickup['lng'],
                    (float) $dest['lat'],
                    (float) $dest['lng'],
                    'driving',
                );
            } catch (\Throwable) {
                $etaRefresh = null;
            }
        }

        return response()->json([
            'data' => [
                'booking'           => $b,
                'live_eta_refresh'  => $etaRefresh,
                'encoded_polyline'  => $b->route_data['encoded_polyline'] ?? null,
            ],
        ]);
    }
}
