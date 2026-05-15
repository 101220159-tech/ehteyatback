# Schedule Management — Provider Dashboard

Backend (Laravel) + React module for provider calendar and booking workflow.

## Backend structure

```
app/
├── Events/ProviderScheduleUpdated.php      # Reverb broadcast: schedule.updated
├── Http/
│   ├── Controllers/Api/V1/Provider/
│   │   ├── ProviderBookingController.php   # accept → creates schedule
│   │   └── ProviderScheduleController.php  # schedule list + calendar events
│   └── Resources/
│       ├── BookingResource.php               # + booking_date, booking_time, address, notes
│       └── ProviderScheduleResource.php
├── Models/ProviderSchedule.php
└── Services/ProviderScheduleService.php    # conflict detection, sync from booking

database/migrations/2026_05_15_000001_create_provider_schedules_table.php

routes/api.php                              # provider schedule routes
routes/channels.php                         # private provider.{providerId}
```

## Frontend structure

```
frontend/provider-schedule/src/
├── api/scheduleApi.ts
├── components/
│   ├── BookingCard.tsx
│   ├── DailyScheduleView.tsx
│   ├── ScheduleEmptyState.tsx
│   ├── ScheduleSkeleton.tsx
│   ├── ScheduleToolbar.tsx
│   ├── StatusBadge.tsx
│   └── WeeklyCalendarView.tsx
├── constants/statusColors.ts
├── hooks/
│   ├── useProviderSchedule.ts
│   └── useScheduleRealtime.ts
├── pages/ProviderSchedulePage.tsx
└── types/schedule.ts
```

## Database

### `bookings` (existing)

Uses `scheduled_at` (datetime) instead of separate `booking_date` / `booking_time`. API resources expose:

- `booking_date` → `Y-m-d`
- `booking_time` → `H:i`
- `address` → `customer_address`
- `notes` → `customer_notes`

### `provider_schedules` (new)

| Column | Type |
|--------|------|
| id | uuid |
| provider_id | uuid FK |
| booking_id | uuid FK (unique) |
| scheduled_date | date |
| scheduled_time | time |
| duration_minutes | smallint |
| status | pending, accepted, completed, cancelled |

## API endpoints (Sanctum + `role:provider`)

Base: `http://127.0.0.1:8000/api/v1`

| Method | Path | Description |
|--------|------|-------------|
| GET | `/provider/schedule` | List with filters |
| GET | `/provider/calendar/events` | Calendar feed |
| POST | `/provider/booking/{id}/accept` | Accept + create schedule |
| POST | `/provider/booking/{id}/complete` | Complete + update schedule |
| POST | `/provider/booking/{id}/cancel` | Cancel booking + schedule |
| PUT | `/provider/bookings/{id}/accept` | Same as POST (legacy) |

### GET `/provider/schedule`

Query: `date`, `from`, `to`, `status`, `search`, `view` (`daily`|`weekly`)

**Example response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "booking_id": "uuid",
      "scheduled_date": "2026-05-20",
      "scheduled_time": "10:00",
      "status": "accepted",
      "customer_name": "Ali Hassan",
      "service_name": "Plumbing Repair",
      "address": "123 Main St, Beirut",
      "notes": "Ring the bell",
      "starts_at": "2026-05-20T10:00:00+00:00",
      "ends_at": "2026-05-20T11:00:00+00:00"
    }
  ],
  "meta": {
    "view": "daily",
    "occupied_time_slots": ["10:00", "10:30"],
    "new_accepted_count": 2,
    "total": 1
  }
}
```

### POST `/provider/booking/{id}/accept`

**Example response:**

```json
{
  "message": "Booking accepted and added to your schedule.",
  "data": { "id": "...", "status": "accepted", "booking_date": "2026-05-20", "booking_time": "10:00" },
  "schedule": { "id": "...", "status": "accepted", "customer_name": "Ali Hassan" }
}
```

**422 conflict:**

```json
{
  "message": "This time slot conflicts with an existing booking.",
  "errors": { "scheduled_at": ["This time slot conflicts with an existing booking."] }
}
```

### GET `/provider/calendar/events?start=2026-05-12&end=2026-05-18`

**Example response:**

```json
{
  "data": [
    {
      "id": "schedule-uuid",
      "title": "Ali Hassan — Plumbing Repair",
      "start": "2026-05-20T10:00:00+00:00",
      "end": "2026-05-20T11:00:00+00:00",
      "status": "accepted",
      "backgroundColor": "#3B82F6",
      "borderColor": "#3B82F6",
      "extendedProps": { }
    }
  ]
}
```

## Accept booking workflow

1. Provider calls `POST /provider/booking/{id}/accept`
2. Backend validates no overlapping `accepted` bookings / schedules
3. Updates `bookings.status` → `accepted`
4. Creates/updates `provider_schedules` row from `scheduled_at`
5. Broadcasts `schedule.updated` on `private-provider.{providerId}`
6. Returns booking + schedule JSON

## Realtime (Laravel Echo + Reverb)

Channel: `private-provider.{providerId}`  
Event: `.schedule.updated`

```javascript
Echo.private(`provider.${providerId}`)
  .listen('.schedule.updated', (e) => {
    console.log(e.schedule);
  });
```

## Status colors (UI)

| Status | Color |
|--------|-------|
| pending | Yellow `#EAB308` |
| accepted | Blue `#3B82F6` |
| completed | Green `#22C55E` |
| cancelled | Red `#EF4444` |

## FullCalendar integration (optional)

```tsx
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';

<FullCalendar
  plugins={[dayGridPlugin, timeGridPlugin]}
  initialView="timeGridWeek"
  events={async (info, success) => {
    const res = await scheduleApi.getCalendarEvents(token, {
      start: info.startStr.slice(0, 10),
      end: info.endStr.slice(0, 10),
    });
    success(res.data);
  }}
  eventClick={(arg) => console.log(arg.event.extendedProps)}
/>
```

## Dashboard design notes

- Card-based layout with `rounded-2xl`, soft shadows, slate palette
- Toolbar: view toggle, date picker, status filter, search
- Badge for `meta.new_accepted_count`
- Occupied slots banner to prevent double booking in UI
- Mobile: single column cards; weekly view horizontal scroll
