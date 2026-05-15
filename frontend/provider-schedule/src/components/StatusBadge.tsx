import { STATUS_COLORS } from '../constants/statusColors';
import type { ScheduleStatus } from '../types/schedule';

export function StatusBadge({ status }: { status: ScheduleStatus }) {
  const c = STATUS_COLORS[status];
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${c.bg} ${c.text} ${c.border}`}
    >
      {c.label}
    </span>
  );
}
