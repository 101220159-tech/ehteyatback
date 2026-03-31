<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Notification::query()
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 30));

        return NotificationResource::collection($items)->response();
    }

    public function send(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'data' => ['nullable', 'array'],
        ]);

        $count = 0;
        foreach ($data['user_ids'] as $uid) {
            $user = User::query()->find($uid);
            if ($user) {
                $notifications->sendInApp($user, $data['type'], $data['title'], $data['content'], $data['data'] ?? null);
                $count++;
            }
        }

        return response()->json(['message' => "Sent {$count} notification(s)."]);
    }
}
