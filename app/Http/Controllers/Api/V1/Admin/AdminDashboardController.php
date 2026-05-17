<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected StatisticsService $statistics,
    ) {}

    public function stats(): JsonResponse
    {
        return response()->json($this->statistics->adminLegacyStats());
    }
}
