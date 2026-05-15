<?php

namespace Tests\Feature;

use App\Services\GoogleMapsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleMapsRoutesApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_maps.key' => 'test-google-key']);
    }

    public function test_directions_uses_routes_api_compute_routes(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 5000,
                    'duration' => '600s',
                    'polyline' => ['encodedPolyline' => '_test_polyline_'],
                    'viewport' => [
                        'low' => ['latitude' => 33.1, 'longitude' => 35.1],
                        'high' => ['latitude' => 33.2, 'longitude' => 35.2],
                    ],
                ]],
            ], 200),
        ]);

        $svc = app(GoogleMapsService::class);
        $out = $svc->directions(33.15, 35.15, 33.16, 35.16, 'driving');

        $this->assertSame(5.0, $out['distance_km']);
        $this->assertSame(10.0, $out['duration_minutes']);
        $this->assertSame('_test_polyline_', $out['encoded_polyline']);
        $this->assertSame(33.2, $out['bounds']['northeast']['lat']);
        $this->assertFalse($out['approximate_route']);
        $this->assertSame('google_routes', $out['route_source']);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://routes.googleapis.com/directions/v2:computeRoutes') {
                return false;
            }
            $mask = $request->header('X-Goog-FieldMask')[0] ?? '';

            return str_contains($mask, 'polyline.encodedPolyline')
                && data_get($request->data(), 'travelMode') === 'DRIVE';
        });
    }

    public function test_directions_straight_line_fallback_when_routes_api_fails(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Routes API disabled'],
            ], 403),
        ]);

        config([
            'transport.routes_fallback.enabled' => true,
            'transport.osrm.enabled' => false,
        ]);

        $svc = app(GoogleMapsService::class);
        $out = $svc->directions(33.8795, 35.5054, 33.8903, 35.4717, 'driving');

        $this->assertTrue($out['approximate_route']);
        $this->assertSame('straight_line_estimate', $out['route_source']);
        $this->assertNotEmpty($out['encoded_polyline']);
        $this->assertGreaterThan(0, $out['distance_km']);
        $this->assertGreaterThan(0, $out['duration_minutes']);
    }

    public function test_directions_osrm_road_route_when_google_routes_fails(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Routes API disabled'],
            ], 403),
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 2500,
                    'duration' => 300,
                    'geometry' => '_osrm_encoded_polyline_',
                    'bbox' => [35.47, 33.87, 35.51, 33.89],
                ]],
            ], 200),
        ]);

        config([
            'transport.routes_fallback.enabled' => true,
            'transport.osrm.enabled' => true,
        ]);

        $svc = app(GoogleMapsService::class);
        $out = $svc->directions(33.8795, 35.5054, 33.8903, 35.4717, 'driving');

        $this->assertSame('osrm', $out['route_source']);
        $this->assertFalse($out['approximate_route']);
        $this->assertSame('_osrm_encoded_polyline_', $out['encoded_polyline']);
        $this->assertSame(2.5, $out['distance_km']);
        $this->assertSame(5.0, $out['duration_minutes']);
    }

    public function test_directions_rethrows_when_fallback_disabled(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response(['error' => ['message' => 'fail']], 403),
        ]);

        config(['transport.routes_fallback.enabled' => false]);

        $svc = app(GoogleMapsService::class);

        $this->expectException(\RuntimeException::class);
        $svc->directions(33.87, 35.50, 33.89, 35.47, 'driving');
    }

    public function test_distance_matrix_wrapper_uses_minimal_field_mask(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => 1000,
                    'duration' => '120s',
                ]],
            ], 200),
        ]);

        $svc = app(GoogleMapsService::class);
        $out = $svc->distanceMatrix(33.0, 35.0, 33.01, 35.01, 'driving');

        $this->assertSame(1.0, $out['distance_km']);
        $this->assertSame(2.0, $out['duration_minutes']);

        Http::assertSent(function ($request) {
            $mask = $request->header('X-Goog-FieldMask')[0] ?? '';

            return str_contains($mask, 'routes.duration')
                && str_contains($mask, 'routes.distanceMeters')
                && ! str_contains($mask, 'polyline');
        });
    }
}
