<?php

namespace App\Services;

use App\Events\ChatMessageCreated;
use App\Mail\NewMessageNotification;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ChatNotificationService
{
    public function __construct(
        protected FirebaseService $firebase,
    ) {}

    public function notifyNewMessage(Message $message): void
    {
        $message->loadMissing(['chat.client', 'chat.provider.user']);

        try {
            broadcast(new ChatMessageCreated($message))->toOthers();
        } catch (Throwable $e) {
            Log::warning('Chat message broadcast failed.', ['exception' => $e->getMessage()]);
        }

        try {
            $chat = $message->chat;
            if (! $chat) {
                return;
            }

            $senderId = (int) $message->sender_id;
            $isClientSender = $senderId === (int) $chat->client_id;

            $recipientUser = $isClientSender
                ? $chat->provider?->user
                : $chat->client;

            $senderLabel = $isClientSender
                ? ($chat->client?->name ?? __('Customer'))
                : ($chat->provider?->user?->name ?? __('Provider'));

            if ($recipientUser) {
                Mail::to($recipientUser->email)->queue(new NewMessageNotification(
                    $recipientUser->name,
                    $senderLabel,
                    (string) $message->message_text,
                    (int) $chat->id
                ));

                $this->firebase->sendToUser(
                    $recipientUser,
                    __('New message'),
                    \Illuminate\Support\Str::limit(strip_tags((string) $message->message_text), 120),
                    ['type' => 'chat_message', 'chat_id' => (string) $chat->id]
                );
            }
        } catch (Throwable $e) {
            Log::error('Chat notification pipeline failed.', ['exception' => $e->getMessage()]);
        }
    }
}
