import { format, parseISO } from 'date-fns';
import { StatusBadge } from './StatusBadge';
import type { ProviderScheduleEntry } from '../types/schedule';

interface Props {
  entry: ProviderScheduleEntry;
  onComplete?: (bookingId: string) => void;
  onCancel?: (bookingId: string) => void;
  disabled?: boolean;
}

export function BookingCard({ entry, onComplete, onCancel, disabled }: Props) {
  const dateLabel = format(parseISO(entry.scheduled_date), 'EEE, MMM d, yyyy');

  return (
    <article className="rounded-2xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-200/50 transition hover:shadow-lg">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold text-slate-900">
            {entry.customer_name ?? 'Customer'}
          </h3>
          <p className="text-sm text-slate-500">{entry.service_name ?? 'Service'}</p>
        </div>
        <StatusBadge status={entry.status} />
      </div>

      <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-slate-400">Date</dt>
          <dd className="font-medium text-slate-700">{dateLabel}</dd>
        </div>
        <div>
          <dt className="text-slate-400">Time</dt>
          <dd className="font-medium text-slate-700">{entry.scheduled_time}</dd>
        </div>
        <div className="sm:col-span-2">
          <dt className="text-slate-400">Address</dt>
          <dd className="font-medium text-slate-700">{entry.address ?? '—'}</dd>
        </div>
        {entry.notes && (
          <div className="sm:col-span-2">
            <dt className="text-slate-400">Notes</dt>
            <dd className="text-slate-600">{entry.notes}</dd>
          </div>
        )}
      </dl>

      {entry.status === 'accepted' && (onComplete || onCancel) && (
        <div className="mt-4 flex flex-wrap gap-2">
          {onComplete && (
            <button
              type="button"
              disabled={disabled}
              onClick={() => onComplete(entry.booking_id)}
              className="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
            >
              Mark completed
            </button>
          )}
          {onCancel && (
            <button
              type="button"
              disabled={disabled}
              onClick={() => onCancel(entry.booking_id)}
              className="rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
            >
              Cancel
            </button>
          )}
        </div>
      )}
    </article>
  );
}
