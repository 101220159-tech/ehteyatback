<?php

namespace App\Events;

use App\Http\Resources\ProviderScheduleResource;
use App\Models\ProviderSchedule;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProviderScheduleUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProviderSchedule $schedule)
    {
        $this->schedule->loadMissing(['booking.customer', 'booking.service']);
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('provider.'.$this->schedule->provider_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'schedule.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'schedule' => (new ProviderScheduleResource($this->schedule))->resolve(),
        ];
    }
}
