<?php

use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{chatId}', function (User $user, int $chatId) {
    $chat = Chat::query()->find($chatId);
    if (! $chat) {
        return false;
    }
    if ((int) $user->id === (int) $chat->client_id) {
        return true;
    }
    $provider = $user->provider;
    if ($provider && (int) $provider->id === (int) $chat->provider_id) {
        return true;
    }

    return false;
});

Broadcast::channel('chat-presence.{chatId}', function (User $user, int $chatId) {
    $chat = Chat::query()->find($chatId);
    if (! $chat) {
        return false;
    }
    if ((int) $user->id === (int) $chat->client_id) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => 'customer'];
    }
    $provider = $user->provider;
    if ($provider && (int) $provider->id === (int) $chat->provider_id) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => 'provider'];
    }

    return false;
});
