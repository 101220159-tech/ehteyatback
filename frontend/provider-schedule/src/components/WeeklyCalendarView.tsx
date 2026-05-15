import {
  addDays,
  format,
  parseISO,
  startOfWeek,
  isSameDay,
} from 'date-fns';
import { StatusBadge } from './StatusBadge';
import { ScheduleEmptyState } from './ScheduleEmptyState';
import type { CalendarEvent } from '../types/schedule';

interface Props {
  weekStart: string;
  events: CalendarEvent[];
}

/**
 * Lightweight weekly grid (no FullCalendar dependency).
 * For FullCalendar, map `events` from GET /provider/calendar/events — see docs/SCHEDULE_MANAGEMENT.md.
 */
export function WeeklyCalendarView({ weekStart, events }: Props) {
  const start = startOfWeek(parseISO(weekStart), { weekStartsOn: 1 });
  const days = Array.from({ length: 7 }, (_, i) => addDays(start, i));

  if (events.length === 0) {
    return <ScheduleEmptyState view="weekly" />;
  }

  return (
    <div className="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm">
      <div className="grid min-w-[640px] grid-cols-7 divide-x divide-slate-100">
        {days.map((day) => {
          const dayEvents = events.filter((e) =>
            isSameDay(parseISO(e.start), day)
          );
          return (
            <div key={day.toISOString()} className="min-h-[200px] p-2">
              <p className="mb-2 text-center text-xs font-semibold uppercase text-slate-500">
                {format(day, 'EEE d')}
              </p>
              <div className="space-y-2">
                {dayEvents.map((ev) => (
                  <div
                    key={ev.id}
                    className="rounded-lg border-l-4 p-2 text-xs shadow-sm"
                    style={{
                      borderLeftColor: ev.borderColor,
                      backgroundColor: `${ev.backgroundColor}22`,
                    }}
                  >
                    <p className="font-medium text-slate-800 line-clamp-2">{ev.title}</p>
                    <p className="mt-1 text-slate-500">
                      {format(parseISO(ev.start), 'HH:mm')} –{' '}
                      {format(parseISO(ev.end), 'HH:mm')}
                    </p>
                    <div className="mt-1">
                      <StatusBadge status={ev.status} />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
