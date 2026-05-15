export type ScheduleStatus = 'pending' | 'accepted' | 'completed' | 'cancelled';

export interface ProviderScheduleEntry {
  id: string;
  provider_id: string;
  booking_id: string;
  scheduled_date: string;
  scheduled_time: string;
  duration_minutes: number;
  status: ScheduleStatus;
  starts_at: string;
  ends_at: string;
  customer_name: string | null;
  service_name: string | null;
  address: string | null;
  notes: string | null;
  booking?: Record<string, unknown> | null;
}

export interface ScheduleListResponse {
  data: ProviderScheduleEntry[];
  meta: {
    view: 'daily' | 'weekly';
    occupied_time_slots: string[];
    new_accepted_count: number;
    total: number;
  };
}

export interface CalendarEvent {
  id: string;
  title: string;
  start: string;
  end: string;
  status: ScheduleStatus;
  backgroundColor: string;
  borderColor: string;
  extendedProps: ProviderScheduleEntry;
}

export interface CalendarEventsResponse {
  data: CalendarEvent[];
}
