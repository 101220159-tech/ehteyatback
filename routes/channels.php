<?php

use App\Models\AdminChatGroup;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{chatId}', function (User $user, string $chatId) {
    if ($user->hasRole(['super_admin', 'admin'])) {
        return true;
    }

    $chat = Chat::query()->find($chatId);
    if ($chat) {
        if ($user->id === $chat->customer_id) {
            return true;
        }
        $provider = $user->provider;
        if ($provider && $provider->id === $chat->provider_id) {
            return true;
        }

        return false;
    }

    $group = AdminChatGroup::query()->find($chatId);

    return $group && $group->users()->whereKey($user->id)->exists();
});
