<?php

namespace App\Traits;

use App\Models\Notification as InAppNotification;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasNotifications
{
    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class, 'user_id');
    }

    public function unreadInAppNotifications(): HasMany
    {
        return $this->inAppNotifications()->where('is_read', false);
    }

    public function markAllNotificationsRead(): int
    {
        return $this->inAppNotifications()
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }
}
