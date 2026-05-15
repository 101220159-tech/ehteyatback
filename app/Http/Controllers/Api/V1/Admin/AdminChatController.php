<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Http\Controllers\Concerns\BroadcastsChatEventWithSocket;
use App\Http\Controllers\Concerns\PaginatesChatMessages;
use App\Http\Controllers\Concerns\ResolvesIdempotentChatMessages;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminChatController extends Controller
{
    use BroadcastsChatEventWithSocket, PaginatesChatMessages, ResolvesIdempotentChatMessages;

    /**
     * Open or create a customer–provider chat thread (admin / super_admin).
     * Accepts the same idea as the customer "start chat" flow, but both sides are explicit.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:users,id'],
            'provider_id' => ['required', 'uuid', 'exists:providers,id'],
        ]);

        User::query()->whereKey($data['customer_id'])->firstOrFail();
        Provider::query()->findOrFail($data['provider_id']);

        $chat = Chat::query()->firstOrCreate([
            'customer_id' => $data['customer_id'],
            'provider_id' => $data['provider_id'],
        ]);

        $code = $chat->wasRecentlyCreated ? 201 : 200;

        return (new ChatResource($chat->load(['customer', 'provider.user'])))->response()->setStatusCode($code);
    }

    /**
     * List all customer–provider chats (admin / super_admin).
     */
    public function index(Request $request): JsonResponse
    {
        $items = Chat::query()
            ->with(['customer', 'provider.user'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')])
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 15));

        return ChatResource::collection($items)->response();
    }

    /**
     * Paginated messages for a chat (read-only moderation view).
     */
    public function messages(Request $request, string $id): JsonResponse
    {
        $chat = Chat::query()->findOrFail($id);

        $messages = $this->paginateChatThreadMessages($chat, $request);

        return MessageResource::collection($messages)->response();
    }

    public function sendMessage(MessageRequest $request, string $id): JsonResponse
    {
        $chat = Chat::query()->findOrFail($id);

        $lockKey = 'chat_msg_send:'.hash('sha256', $chat->id.'|'.$request->user()->id);

        return Cache::lock($lockKey, 8)->block(5, function () use ($request, $chat) {
            if ($retry = $this->idempotentMessageIfRetry($request, $chat)) {
                return $retry;
            }
            if ($dup = $this->duplicateMessageFromRapidResend($request, $chat)) {
                return $dup;
            }

            $message = Message::query()->create([
                'chat_id'   => $chat->id,
                'sender_id' => $request->user()->id,
                'body'      => $request->input('body'),
                'type'      => $request->input('type', 'text'),
            ]);

            $chat->update(['last_message_at' => now()]);

            $this->rememberIdempotentChatMessage($request, $chat, $message);
            $this->broadcastChatEventWithSocket($request, fn () => new MessageSent($message));

            return (new MessageResource($message))->response()->setStatusCode(201);
        });
    }

    public function markMessageRead(Request $request, string $id, string $msgId): JsonResponse
    {
        $chat = Chat::query()->findOrFail($id);

        $message = Message::query()
            ->where('chat_id', $chat->id)
            ->findOrFail($msgId);

        $message->update(['read_at' => now()]);

        $chatId = (string) $chat->id;
        $messageId = (string) $message->id;
        $readerId = (string) $request->user()->id;
        $this->broadcastChatEventWithSocket(
            $request,
            fn () => new MessageRead($chatId, $messageId, $readerId)
        );

        return response()->json(['ok' => true]);
    }
}
