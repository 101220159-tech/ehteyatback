<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\MessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait ResolvesIdempotentChatMessages
{
    /**
     * If the client sends `Idempotency-Key` (e.g. UUID) and retries the same POST, return the
     * original message without creating a duplicate row (React Strict Mode / double tap).
     */
    protected function idempotentMessageIfRetry(MessageRequest $request, Chat $chat): ?JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '' || strlen($key) > 200) {
            return null;
        }

        $cacheKey = 'chat_msg:idem:'.hash('sha256', $request->user()->id.'|'.$chat->id.'|'.$key);
        $messageId = Cache::get($cacheKey);
        if (! is_string($messageId) && ! is_int($messageId)) {
            return null;
        }

        $message = Message::query()->find($messageId);

        return $message
            ? (new MessageResource($message))->response()->setStatusCode(200)
            : null;
    }

    protected function rememberIdempotentChatMessage(Request $request, Chat $chat, Message $message): void
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '' || strlen($key) > 200) {
            return;
        }

        $cacheKey = 'chat_msg:idem:'.hash('sha256', $request->user()->id.'|'.$chat->id.'|'.$key);
        Cache::put($cacheKey, (string) $message->id, now()->addDay());
    }

    /**
     * Same chat + same sender + same body within a short window → treat as accidental double POST
     * (no Idempotency-Key header). Returns existing message, no second DB row / broadcast.
     */
    protected function duplicateMessageFromRapidResend(MessageRequest $request, Chat $chat): ?JsonResponse
    {
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return null;
        }

        $duplicate = Message::query()
            ->where('chat_id', $chat->id)
            ->where('sender_id', $request->user()->id)
            ->where('body', $body)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->orderByDesc('created_at')
            ->first();

        return $duplicate
            ? (new MessageResource($duplicate))->response()->setStatusCode(200)
            : null;
    }
}
