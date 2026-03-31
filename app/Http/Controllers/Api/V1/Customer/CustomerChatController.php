<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Events\ChatMessageRead;
use App\Events\ChatTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Provider;
use App\Services\ChatNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerChatController extends Controller
{
    public function __construct(
        protected ChatNotificationService $chatNotifications,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
        ]);

        return $this->openWithProvider($request, (int) $data['provider_id']);
    }

    public function index(Request $request): JsonResponse
    {
        $items = Chat::query()
            ->where('client_id', $request->user()->id)
            ->with(['provider.user'])
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 15));

        return ChatResource::collection($items)->response();
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $chat = Chat::query()
            ->where('client_id', $request->user()->id)
            ->findOrFail($id);

        $messages = $chat->messages()->paginate($request->integer('per_page', 50));

        return MessageResource::collection($messages)->response();
    }

    public function sendMessage(MessageRequest $request, int $id): JsonResponse
    {
        $chat = Chat::query()
            ->where('client_id', $request->user()->id)
            ->findOrFail($id);

        $message = Message::query()->create([
            'chat_id' => $chat->id,
            'sender_id' => $request->user()->id,
            'message_text' => $request->input('message_text'),
            'message_type' => $request->input('message_type'),
        ]);

        $chat->touch();
        $this->chatNotifications->notifyNewMessage($message);

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    public function typing(Request $request, int $id): JsonResponse
    {
        $chat = Chat::query()
            ->where('client_id', $request->user()->id)
            ->findOrFail($id);

        $typing = $request->boolean('typing');
        broadcast(new ChatTyping(
            (int) $chat->id,
            (int) $request->user()->id,
            (string) $request->user()->name,
            $typing
        ))->toOthers();

        return response()->json(['ok' => true]);
    }

    public function markMessageRead(Request $request, int $id, int $messageId): JsonResponse
    {
        $chat = Chat::query()
            ->where('client_id', $request->user()->id)
            ->findOrFail($id);

        $message = Message::query()
            ->where('chat_id', $chat->id)
            ->findOrFail($messageId);

        DB::transaction(function () use ($message) {
            $message->update(['is_read' => true, 'read_at' => now()]);
        });

        broadcast(new ChatMessageRead(
            (int) $chat->id,
            (int) $message->id,
            (int) $request->user()->id
        ))->toOthers();

        return response()->json(['ok' => true]);
    }

    private function openWithProvider(Request $request, int $providerId): JsonResponse
    {
        Provider::query()->findOrFail($providerId);
        $chat = Chat::query()->firstOrCreate(
            [
                'client_id' => $request->user()->id,
                'provider_id' => $providerId,
            ],
        );

        return (new ChatResource($chat->load(['provider.user'])))->response();
    }
}
