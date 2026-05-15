<?php

return [
    /*
    | When Google Routes API fails: optional OSRM (OpenStreetMap) driving route — real road geometry,
    | Google-compatible encoded polyline. Default uses the public demo server; use your own OSRM URL in production.
    | See https://project-osrm.org/
    */
    'osrm' => [
        'enabled' => env('TRANSPORT_OSRM_FALLBACK_ENABLED', true),
        'base_url' => rtrim(env('TRANSPORT_OSRM_BASE_URL', 'https://router.project-osrm.org/route/v1'), '/'),
    ],

    /*
    | When Google Routes API is disabled or fails, fall back to Haversine distance + straight-line
    | polyline + ETA from assumed average speed (km/h). Lets taxi/delivery estimates work without Routes API.
    */
    'routes_fallback' => [
        'enabled' => env('TRANSPORT_ROUTES_FALLBACK_ENABLED', true),
        'assumed_speed_kmh' => env('TRANSPORT_FALLBACK_SPEED_KMH', 28),
    ],

    /*
    | Optional: platform commission on transport estimates (for dashboards / transparency).
    | Customer pays estimated_price / shipping_price; partner_payout_estimate is shown only when percent > 0.
    */
    'platform' => [
        'commission_percent' => (float) env('TRANSPORT_PLATFORM_COMMISSION_PERCENT', 0),
    ],

    /*
    | Simple estimate pricing (adjust per region). Uses distance + duration from routing (Google or fallback).
    */
    'taxi' => [
        'base_fee'           => env('TRANSPORT_TAXI_BASE_FEE', 3.50),
        'per_km'             => env('TRANSPORT_TAXI_PER_KM', 1.20),
        'per_minute'         => env('TRANSPORT_TAXI_PER_MIN', 0.35),
        'vehicle_multiplier' => [
            'standard' => 1.0,
            'sedan'    => 1.0,
            'suv'      => 1.15,
            'van'      => 1.25,
            'premium'  => 1.35,
        ],
    ],

    'delivery' => [
        'base_fee' => [
            'motorcycle' => env('TRANSPORT_DELIVERY_BASE_MOTORCYCLE', 4.00),
            'car'        => env('TRANSPORT_DELIVERY_BASE_CAR', 6.00),
            'truck'      => env('TRANSPORT_DELIVERY_BASE_TRUCK', 12.00),
        ],
        'per_km' => [
            'motorcycle' => env('TRANSPORT_DELIVERY_PER_KM_MOTORCYCLE', 0.65),
            'car'        => env('TRANSPORT_DELIVERY_PER_KM_CAR', 0.90),
            'truck'      => env('TRANSPORT_DELIVERY_PER_KM_TRUCK', 1.40),
        ],
        /** kg above this with motorcycle → suggest car */
        'motorcycle_max_kg' => env('TRANSPORT_MOTORCYCLE_MAX_KG', 15),
        /* +$ per kg over 5kg for car/truck */
        'weight_surcharge_per_kg' => env('TRANSPORT_WEIGHT_SURCHARGE', 0.25),
        'fragile_multiplier'      => env('TRANSPORT_FRAGILE_MULTIPLIER', 1.12),
    ],
];
