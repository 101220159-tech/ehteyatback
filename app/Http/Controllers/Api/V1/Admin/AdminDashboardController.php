<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'users_count' => User::query()->count(),
            'providers_count' => Provider::query()->count(),
            'services_count' => Service::query()->count(),
            'bookings_pending' => Booking::query()->where('status', 'pending')->count(),
            'completed_bookings_count' => Booking::query()->where('status', 'completed')->count(),
        ]);
    }
}
