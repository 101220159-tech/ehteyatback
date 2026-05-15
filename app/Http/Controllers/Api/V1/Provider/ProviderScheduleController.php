<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderScheduleResource;
use App\Models\ProviderSchedule;
use App\Services\ProviderScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderScheduleController extends Controller
{
    public function __construct(private ProviderScheduleService $scheduleService) {}

    private function provider(Request $request)
    {
        $p = $request->user()->provider;
        abort_if(! $p, 404, 'Provider profile not found.');

        return $p;
    }

    /**
     * GET /provider/schedule — daily list with filters & search.
     */
    public function index(Request $request): JsonResponse
    {
        $provider = $this->provider($request);

        $data = $request->validate([
            'date'   => 'nullable|date',
            'from'   => 'nullable|date',
            'to'     => 'nullable|date|after_or_equal:from',
            'status' => 'nullable|in:pending,accepted,completed,cancelled',
            'search' => 'nullable|string|max:100',
            'view'   => 'nullable|in:daily,weekly',
        ]);

        $query = ProviderSchedule::query()
            ->where('provider_id', $provider->id)
            ->with(['booking.customer', 'booking.service'])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['date'])) {
            $query->whereDate('scheduled_date', $data['date']);
        } elseif (($data['view'] ?? null) === 'weekly' && ! empty($data['from'])) {
            $start = Carbon::parse($data['from'])->startOfWeek();
            $end   = $start->copy()->endOfWeek();
            $query->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()]);
        } elseif (! empty($data['from']) && ! empty($data['to'])) {
            $query->whereBetween('scheduled_date', [$data['from'], $data['to']]);
        } elseif (! empty($data['from'])) {
            $query->whereDate('scheduled_date', '>=', $data['from']);
        }

        if (! empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->whereHas('booking', function ($q) use ($term) {
                $q->whereHas('customer', fn ($c) => $c->where('name', 'like', $term))
                    ->orWhereHas('service', fn ($s) => $s->where('name', 'like', $term))
                    ->orWhere('customer_address', 'like', $term)
                    ->orWhere('customer_notes', 'like', $term);
            });
        }

        $schedules = $query->get();
        $occupied  = [];

        if (! empty($data['date'])) {
            $occupied = $this->scheduleService->occupiedTimeSlots($provider->id, $data['date']);
        }

        $newAcceptedCount = ProviderSchedule::query()
            ->where('provider_id', $provider->id)
            ->where('status', 'accepted')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return response()->json([
            'data' => ProviderScheduleResource::collection($schedules),
            'meta' => [
                'view'                 => $data['view'] ?? 'daily',
                'occupied_time_slots'  => $occupied,
                'new_accepted_count'   => $newAcceptedCount,
                'total'                => $schedules->count(),
            ],
        ]);
    }

    /**
     * GET /provider/calendar/events — FullCalendar-style event feed.
     */
    public function calendarEvents(Request $request): JsonResponse
    {
        $provider = $this->provider($request);

        $data = $request->validate([
            'start'  => 'required|date',
            'end'    => 'required|date|after_or_equal:start',
            'status' => 'nullable|in:pending,accepted,completed,cancelled',
        ]);

        $query = ProviderSchedule::query()
            ->where('provider_id', $provider->id)
            ->whereBetween('scheduled_date', [
                Carbon::parse($data['start'])->toDateString(),
                Carbon::parse($data['end'])->toDateString(),
            ])
            ->with(['booking.customer', 'booking.service'])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $events = $query->get()->map(function (ProviderSchedule $schedule) {
            $startsAt = Carbon::parse(
                $schedule->scheduled_date->format('Y-m-d').' '.$schedule->scheduled_time
            );
            $resource = (new ProviderScheduleResource($schedule))->resolve();

            return [
                'id'              => $schedule->id,
                'title'           => trim(($resource['customer_name'] ?? 'Customer').' — '.($resource['service_name'] ?? 'Service')),
                'start'           => $startsAt->toIso8601String(),
                'end'             => $startsAt->copy()->addMinutes($schedule->duration_minutes)->toIso8601String(),
                'status'          => $schedule->status,
                'backgroundColor' => $this->statusColor($schedule->status),
                'borderColor'     => $this->statusColor($schedule->status),
                'extendedProps'   => $resource,
            ];
        });

        return response()->json(['data' => $events]);
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'pending'   => '#EAB308',
            'accepted'  => '#3B82F6',
            'completed' => '#22C55E',
            'cancelled' => '#EF4444',
            default     => '#94A3B8',
        };
    }
}
