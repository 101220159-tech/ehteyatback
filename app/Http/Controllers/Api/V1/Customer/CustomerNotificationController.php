<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return NotificationResource::collection($items)->response();
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $n = Notification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $n->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked as read.', 'notification' => new NotificationResource($n)]);
    }
}
