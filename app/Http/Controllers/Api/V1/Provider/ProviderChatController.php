<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Events\MessageRead;
use App\Events\MessageSent;
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

class ProviderChatController extends Controller
{
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

        $messages = $chat->messages()->paginate($request->integer('per_page', 50));

        return MessageResource::collection($messages)->response();
    }

    public function sendMessage(MessageRequest $request, string $id): JsonResponse
    {
        $provider = $this->provider($request);

        $chat = Chat::query()
            ->where('provider_id', $provider->id)
            ->findOrFail($id);

        $message = Message::query()->create([
            'chat_id'   => $chat->id,
            'sender_id' => $request->user()->id,
            'body'      => $request->input('body'),
            'type'      => $request->input('type', 'text'),
        ]);

        $chat->update(['last_message_at' => now()]);

        broadcast(new MessageSent($message))->toOthers();

        // Notify customer if this is their first unread message
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

        broadcast(new MessageRead($chat->id, $message->id, $request->user()->id));

        return response()->json(['ok' => true]);
    }
}
