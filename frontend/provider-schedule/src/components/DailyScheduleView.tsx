import { BookingCard } from './BookingCard';
import { ScheduleEmptyState } from './ScheduleEmptyState';
import type { ProviderScheduleEntry } from '../types/schedule';

interface Props {
  entries: ProviderScheduleEntry[];
  occupiedSlots: string[];
  onComplete?: (id: string) => void;
  onCancel?: (id: string) => void;
  busy?: boolean;
}

export function DailyScheduleView({
  entries,
  occupiedSlots,
  onComplete,
  onCancel,
  busy,
}: Props) {
  if (entries.length === 0) {
    return <ScheduleEmptyState view="daily" />;
  }

  return (
    <div className="space-y-6">
      {occupiedSlots.length > 0 && (
        <div className="rounded-xl border border-amber-100 bg-amber-50/80 px-4 py-3 text-sm text-amber-900">
          <strong>Occupied slots:</strong>{' '}
          {occupiedSlots.join(', ')} — these times cannot be double-booked.
        </div>
      )}
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {entries.map((entry) => (
          <BookingCard
            key={entry.id}
            entry={entry}
            onComplete={onComplete}
            onCancel={onCancel}
            disabled={busy}
          />
        ))}
      </div>
    </div>
  );
}
