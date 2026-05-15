import type { ScheduleStatus } from '../types/schedule';

export const STATUS_COLORS: Record<
  ScheduleStatus,
  { bg: string; text: string; border: string; label: string }
> = {
  pending: {
    bg: 'bg-amber-50',
    text: 'text-amber-800',
    border: 'border-amber-300',
    label: 'Pending',
  },
  accepted: {
    bg: 'bg-blue-50',
    text: 'text-blue-800',
    border: 'border-blue-300',
    label: 'Accepted',
  },
  completed: {
    bg: 'bg-emerald-50',
    text: 'text-emerald-800',
    border: 'border-emerald-300',
    label: 'Completed',
  },
  cancelled: {
    bg: 'bg-red-50',
    text: 'text-red-800',
    border: 'border-red-300',
    label: 'Cancelled',
  },
};

export const STATUS_FILTER_OPTIONS: { value: '' | ScheduleStatus; label: string }[] = [
  { value: '', label: 'All statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
];
