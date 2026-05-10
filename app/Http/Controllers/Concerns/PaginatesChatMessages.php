<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Chat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

trait PaginatesChatMessages
{
    /**
     * Paginate messages oldest→newest. If `page` is omitted, default to the **last** page so the
     * first request returns the newest window (where a just-sent message lives). Clients that
     * refetch after POST used to hit page 1 = oldest chunk only → UI “lost” the new bubble.
     * Pass `page` explicitly (e.g. 1) for “load from the beginning” behaviour.
     */
    protected function paginateChatThreadMessages(Chat $chat, Request $request): LengthAwarePaginator
    {
        return $this->paginateMessagesForRelation($chat->messages(), $request);
    }

    /**
     * Same pagination rules for admin multi-user groups (AdminChatGroup messages).
     */
    protected function paginateAdminGroupMessages(Relation $messages, Request $request): LengthAwarePaginator
    {
        return $this->paginateMessagesForRelation($messages, $request);
    }

    protected function paginateMessagesForRelation(Relation $messages, Request $request): LengthAwarePaginator
    {
        $perPage = min(max(1, $request->integer('per_page', 50)), 100);
        $query = $messages->orderBy('created_at');

        if (! $request->filled('page')) {
            $total = $messages->count();
            $lastPage = max(1, (int) ceil($total / $perPage));

            return $query->paginate($perPage, ['*'], 'page', $lastPage);
        }

        return $query->paginate($perPage);
    }
}
