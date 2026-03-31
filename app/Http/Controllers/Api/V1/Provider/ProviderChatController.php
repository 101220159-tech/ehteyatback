<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Events\ChatMessageRead;
use App\Events\ChatTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Provider as ProviderModel;
use App\Services\ChatNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderChatController extends Controller
{
    public function __construct(
        protected ChatNotificationService $chatNotifications,
    ) {}

    protected function provider(Request $request): ProviderModel
    {
        $p = $request->user()->provider;
        abort_if(! $p, 404, 'Provider profile not found.');

        return $p;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = $this->provider($request);
        $items = Chat::query()
            ->where('provider_id', $provider->id)
            ->with('client')
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 15));

        return ChatResource::collection($items)->response();
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $provider = $this->provider($request);
        $chat = Chat::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($id);

        $messages = $chat->messages()->paginate($request->integer('per_page', 50));

        return MessageResource::collection($messages)->response();
    }

    public function sendMessage(MessageRequest $request, int $id): JsonResponse
    {
        $provider = $this->provider($request);
        $chat = Chat::query()
            ->where('provider_id', $provider->id)
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
        $provider = $this->provider($request);
        $chat = Chat::query()
            ->where('provider_id', $provider->id)
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
        $provider = $this->provider($request);
        $chat = Chat::query()
            ->where('provider_id', $provider->id)
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
}
