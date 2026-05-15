import { useMemo, useState } from 'react';
import { addDays, format, startOfWeek } from 'date-fns';
import { DailyScheduleView } from '../components/DailyScheduleView';
import { ScheduleSkeleton } from '../components/ScheduleSkeleton';
import { ScheduleToolbar } from '../components/ScheduleToolbar';
import { WeeklyCalendarView } from '../components/WeeklyCalendarView';
import {
  useCalendarEvents,
  useProviderSchedule,
  useScheduleMutations,
} from '../hooks/useProviderSchedule';
import { useScheduleRealtime } from '../hooks/useScheduleRealtime';
import type { ScheduleStatus } from '../types/schedule';

interface Props {
  /** Sanctum bearer token */
  token: string;
  providerId: string;
  /** Optional Laravel Echo instance for live updates */
  echo?: Parameters<typeof useScheduleRealtime>[0];
}

export function ProviderSchedulePage({ token, providerId, echo }: Props) {
  const today = format(new Date(), 'yyyy-MM-dd');
  const [view, setView] = useState<'daily' | 'weekly'>('daily');
  const [date, setDate] = useState(today);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<'' | ScheduleStatus>('');

  const weekStartDate = startOfWeek(new Date(date), { weekStartsOn: 1 });
  const weekStart = format(weekStartDate, 'yyyy-MM-dd');
  const weekEnd = format(addDays(weekStartDate, 6), 'yyyy-MM-dd');

  const query = useMemo(
    () => ({
      date: view === 'daily' ? date : undefined,
      from: view === 'weekly' ? weekStart : undefined,
      view,
      search: search || undefined,
      status: status || undefined,
    }),
    [view, date, weekStart, search, status]
  );

  const { data, isLoading, isError, error } = useProviderSchedule(token, query);
  const calendar = useCalendarEvents(
    token,
    { start: weekStart, end: weekEnd },
    status || undefined
  );
  const { complete, cancel } = useScheduleMutations(token);

  useScheduleRealtime(echo ?? null, providerId);

  return (
    <div className="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <ScheduleToolbar
        view={view}
        onViewChange={setView}
        search={search}
        onSearchChange={setSearch}
        status={status}
        onStatusChange={setStatus}
        date={date}
        onDateChange={setDate}
        newCount={data?.meta.new_accepted_count}
      />

      {isError && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {(error as Error).message}
        </div>
      )}

      {isLoading && <ScheduleSkeleton />}

      {!isLoading && data && view === 'daily' && (
        <DailyScheduleView
          entries={data.data}
          occupiedSlots={data.meta.occupied_time_slots}
          onComplete={(id) => complete.mutate({ id })}
          onCancel={(id) => cancel.mutate({ id })}
          busy={complete.isPending || cancel.isPending}
        />
      )}

      {!isLoading && view === 'weekly' && (
        <WeeklyCalendarView
          weekStart={weekStart}
          events={calendar.data?.data ?? []}
        />
      )}
    </div>
  );
}
