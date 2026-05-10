<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChatGroupMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_chat_group_id',
        'sender_id',
        'body',
        'type',
        'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AdminChatGroup::class, 'admin_chat_group_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
