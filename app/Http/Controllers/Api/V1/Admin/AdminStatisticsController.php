<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStatisticsController extends Controller
{
    public function __construct(
        protected StatisticsService $statistics,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : null;
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : null;

        return response()->json([
            'success' => true,
            'data' => $this->statistics->adminOverview($from, $to),
        ]);
    }

    public function bookingsByDay(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);

        return response()->json([
            'success' => true,
            'data' => $this->statistics->bookingsByDay($days),
        ]);
    }
}
