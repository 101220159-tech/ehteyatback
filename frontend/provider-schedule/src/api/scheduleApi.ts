import type {
  CalendarEventsResponse,
  ScheduleListResponse,
  ScheduleStatus,
} from '../types/schedule';

const API = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api/v1';

function authHeaders(token: string): HeadersInit {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  };
}

async function handle<T>(res: Response): Promise<T> {
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(body?.message ?? `Request failed (${res.status})`);
  }
  return res.json() as Promise<T>;
}

export interface ScheduleQuery {
  date?: string;
  from?: string;
  to?: string;
  status?: ScheduleStatus | '';
  search?: string;
  view?: 'daily' | 'weekly';
}

export const scheduleApi = {
  getSchedule(token: string, params: ScheduleQuery) {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v) q.set(k, String(v));
    });
    return fetch(`${API}/provider/schedule?${q}`, {
      headers: authHeaders(token),
    }).then((r) => handle<ScheduleListResponse>(r));
  },

  getCalendarEvents(
    token: string,
    params: { start: string; end: string; status?: ScheduleStatus }
  ) {
    const q = new URLSearchParams(params as Record<string, string>);
    return fetch(`${API}/provider/calendar/events?${q}`, {
      headers: authHeaders(token),
    }).then((r) => handle<CalendarEventsResponse>(r));
  },

  acceptBooking(token: string, bookingId: string) {
    return fetch(`${API}/provider/booking/${bookingId}/accept`, {
      method: 'POST',
      headers: authHeaders(token),
    }).then((r) => handle(r));
  },

  completeBooking(token: string, bookingId: string, amount?: number) {
    return fetch(`${API}/provider/booking/${bookingId}/complete`, {
      method: 'POST',
      headers: authHeaders(token),
      body: JSON.stringify(amount != null ? { amount } : {}),
    }).then((r) => handle(r));
  },

  cancelBooking(token: string, bookingId: string, reason?: string) {
    return fetch(`${API}/provider/booking/${bookingId}/cancel`, {
      method: 'POST',
      headers: authHeaders(token),
      body: JSON.stringify(reason ? { reason } : {}),
    }).then((r) => handle(r));
  },
};
