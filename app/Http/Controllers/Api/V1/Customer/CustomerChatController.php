<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Provider;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerChatController extends Controller
{
    public function __construct(private NotificationService $notify) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'uuid', 'exists:providers,id'],
        ]);

        Provider::query()->findOrFail($data['provider_id']);

        $chat = Chat::query()->firstOrCreate([
            'customer_id' => $request->user()->id,
            'provider_id' => $data['provider_id'],
        ]);

        return (new ChatResource($chat->load(['provider.user'])))->response();
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $items = Chat::query()
            ->where('customer_id', $userId)
            ->with(['provider.user'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $userId)])
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 15));

        return ChatResource::collection($items)->response();
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $chat = Chat::query()
            ->where('customer_id', $request->user()->id)
            ->findOrFail($id);

        $messages = $chat->messages()->paginate($request->integer('per_page', 50));

        return MessageResource::collection($messages)->response();
    }

    public function sendMessage(MessageRequest $request, string $id): JsonResponse
    {
        $chat = Chat::query()
            ->where('customer_id', $request->user()->id)
            ->findOrFail($id);

        $message = Message::query()->create([
            'chat_id'   => $chat->id,
            'sender_id' => $request->user()->id,
            'body'      => $request->input('body'),
            'type'      => $request->input('type', 'text'),
        ]);

        $chat->update(['last_message_at' => now()]);

        // Broadcast to the chat channel
        broadcast(new MessageSent($message))->toOthers();

        // Notify provider if they have no unread messages in this chat yet
        $chat->load('provider.user');
        if ($chat->provider?->user) {
            $unread = Message::where('chat_id', $chat->id)
                ->whereNull('read_at')
                ->where('sender_id', '!=', $chat->provider->user->id)
                ->count();

            if ($unread === 1) {
                $this->notify->sendInApp(
                    $chat->provider->user,
                    'new_message',
                    'New Message',
                    $request->user()->name.' sent you a message.',
                    ['chat_id' => $chat->id]
                );
            }
        }

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    public function markMessageRead(Request $request, string $id, string $msgId): JsonResponse
    {
        $chat = Chat::query()
            ->where('customer_id', $request->user()->id)
            ->findOrFail($id);

        $message = Message::query()
            ->where('chat_id', $chat->id)
            ->findOrFail($msgId);

        $message->update(['read_at' => now()]);

        broadcast(new MessageRead($chat->id, $message->id, $request->user()->id));

        return response()->json(['ok' => true]);
    }
}
