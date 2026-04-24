<?php

use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{chatId}', function (User $user, string $chatId) {
    $chat = Chat::query()->find($chatId);
    if (! $chat) {
        return false;
    }
    if ($user->id === $chat->customer_id) {
        return true;
    }
    $provider = $user->provider;
    if ($provider && $provider->id === $chat->provider_id) {
        return true;
    }

    return false;
});
