import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { scheduleApi, type ScheduleQuery } from '../api/scheduleApi';
import type { ScheduleStatus } from '../types/schedule';

const KEY = 'provider-schedule';

export function useProviderSchedule(token: string, params: ScheduleQuery) {
  return useQuery({
    queryKey: [KEY, params],
    queryFn: () => scheduleApi.getSchedule(token, params),
    enabled: Boolean(token),
  });
}

export function useCalendarEvents(
  token: string,
  range: { start: string; end: string },
  status?: ScheduleStatus
) {
  return useQuery({
    queryKey: [KEY, 'calendar', range, status],
    queryFn: () => scheduleApi.getCalendarEvents(token, { ...range, status }),
    enabled: Boolean(token),
  });
}

export function useScheduleMutations(token: string) {
  const qc = useQueryClient();

  const invalidate = () => qc.invalidateQueries({ queryKey: [KEY] });

  const accept = useMutation({
    mutationFn: (bookingId: string) => scheduleApi.acceptBooking(token, bookingId),
    onSuccess: invalidate,
  });

  const complete = useMutation({
    mutationFn: ({ id, amount }: { id: string; amount?: number }) =>
      scheduleApi.completeBooking(token, id, amount),
    onSuccess: invalidate,
  });

  const cancel = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason?: string }) =>
      scheduleApi.cancelBooking(token, id, reason),
    onSuccess: invalidate,
  });

  return { accept, complete, cancel };
}
