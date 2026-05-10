<?php

namespace App\Http\Controllers\Api\V1\Provider;

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
use App\Models\Provider as ProviderModel;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProviderChatController extends Controller
{
    use BroadcastsChatEventWithSocket, PaginatesChatMessages, ResolvesIdempotentChatMessages;

    public function __construct(private NotificationService $notify) {}

    protected function provider(Request $request): ProviderModel
    {
        $p = $request->user()->provider;
        abort_if(! $p, 404, 'Provider profile not found.');

        return $p;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $this->provider($request);

        $userId = $request->user()->id;

        $items = Chat::query()
            ->where('provider_id', $provider->id)
            ->with(['customer'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $userId)])
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 15));

        return ChatResource::collection($items)->response();
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);

        $chat = Chat::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($id);

        $messages = $this->paginateChatThreadMessages($chat, $request);

        return MessageResource::collection($messages)->response();
    }

    public function sendMessage(MessageRequest $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);

        $chat = Chat::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($id);

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

            $chat->load('customer');
            if ($chat->customer) {
                $unread = Message::where('chat_id', $chat->id)
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $chat->customer->id)
                    ->count();

                if ($unread === 1) {
                    $this->notify->sendInApp(
                        $chat->customer,
                        'new_message',
                        'New Message',
                        $request->user()->name.' sent you a message.',
                        ['chat_id' => $chat->id]
                    );
                }
            }

            return (new MessageResource($message))->response()->setStatusCode(201);
        });
    }

    public function markMessageRead(Request $request, string $id, string $msgId): JsonResponse
    {
        $provider = $this->provider($request);

        $chat = Chat::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($id);

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
