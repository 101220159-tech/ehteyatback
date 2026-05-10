<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\AdminChatGroupMessageRead;
use App\Events\AdminChatGroupMessageSent;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Http\Controllers\Concerns\BroadcastsChatEventWithSocket;
use App\Http\Controllers\Concerns\PaginatesChatMessages;
use App\Http\Controllers\Concerns\ResolvesIdempotentChatMessages;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
use App\Models\AdminChatGroup;
use App\Models\AdminChatGroupMessage;
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

        $payload = ChatResource::collection($items)->toArray($request);

        $groups = AdminChatGroup::query()
            ->with('users')
            ->orderBy('name')
            ->get()
            ->map(fn (AdminChatGroup $g) => $this->serializeAdminGroup($g));

        $payload['data'] = array_merge(
            $groups->all(),
            $payload['data'] ?? [],
        );

        return response()->json($payload);
    }

    /**
     * Admin-only multi-user groups (name + member_ids) for moderation / broadcasts.
     */
    public function storeGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:160'],
            'member_ids' => ['required', 'array', 'min:2'],
            'member_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $name = isset($data['name']) && $data['name'] !== ''
            ? $data['name']
            : 'Group';

        $memberIds = collect($data['member_ids'])->unique()->values()->all();

        $group = AdminChatGroup::query()->create(['name' => $name]);
        $group->users()->sync($memberIds);
        $group->load('users');

        return response()->json([
            'data' => $this->serializeAdminGroup($group),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAdminGroup(AdminChatGroup $g): array
    {
        return [
            'id' => $g->id,
            'name' => $g->name,
            'group_name' => $g->name,
            'member_ids' => $g->users->pluck('id')->values()->all(),
            'members' => $g->users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values()->all(),
            'unread_count' => 0,
            'last_message_at' => null,
            'customer_id' => null,
            'provider_id' => null,
            'is_admin_group' => true,
            'created_at' => $g->created_at,
            'updated_at' => $g->updated_at,
        ];
    }

    public function groupMessages(Request $request, string $id): JsonResponse
    {
        $group = AdminChatGroup::query()->findOrFail($id);

        $messages = $this->paginateAdminGroupMessages($group->messages(), $request)
            ->through(fn (AdminChatGroupMessage $m) => $this->serializeGroupMessage($m));

        return response()->json($messages);
    }

    public function sendGroupMessage(MessageRequest $request, string $id): JsonResponse
    {
        $group = AdminChatGroup::query()->findOrFail($id);

        $message = AdminChatGroupMessage::query()->create([
            'admin_chat_group_id' => $group->id,
            'sender_id'           => $request->user()->id,
            'body'                => $request->input('body'),
            'type'                => $request->input('type', 'text'),
        ]);

        $this->broadcastChatEventWithSocket($request, fn () => new AdminChatGroupMessageSent($message));

        return response()->json([
            'data' => $this->serializeGroupMessage($message),
        ], 201);
    }

    public function markGroupMessageRead(Request $request, string $id, string $msgId): JsonResponse
    {
        $group = AdminChatGroup::query()->findOrFail($id);

        $message = AdminChatGroupMessage::query()
            ->where('admin_chat_group_id', $group->id)
            ->findOrFail($msgId);

        $message->update(['read_at' => now()]);

        $groupId = (string) $group->id;
        $messageId = (string) $message->id;
        $readerId = (string) $request->user()->id;
        $this->broadcastChatEventWithSocket(
            $request,
            fn () => new AdminChatGroupMessageRead($groupId, $messageId, $readerId)
        );

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGroupMessage(AdminChatGroupMessage $m): array
    {
        return [
            'id'         => $m->id,
            'group_id'   => $m->admin_chat_group_id,
            'chat_id'    => $m->admin_chat_group_id,
            'sender_id'  => $m->sender_id,
            'body'       => $m->body,
            'type'       => $m->type,
            'is_read'    => $m->read_at !== null,
            'read_at'    => $m->read_at,
            'created_at' => $m->created_at,
        ];
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
