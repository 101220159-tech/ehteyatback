<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminChatGroup extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'admin_chat_group_user', 'admin_chat_group_id', 'user_id')
            ->withTimestamps();
    }

    /** @return HasMany<AdminChatGroupMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AdminChatGroupMessage::class, 'admin_chat_group_id')->orderBy('created_at');
    }
}
