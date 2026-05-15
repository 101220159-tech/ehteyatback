import { STATUS_FILTER_OPTIONS } from '../constants/statusColors';
import type { ScheduleStatus } from '../types/schedule';

interface Props {
  view: 'daily' | 'weekly';
  onViewChange: (v: 'daily' | 'weekly') => void;
  search: string;
  onSearchChange: (v: string) => void;
  status: '' | ScheduleStatus;
  onStatusChange: (v: '' | ScheduleStatus) => void;
  date: string;
  onDateChange: (v: string) => void;
  newCount?: number;
}

export function ScheduleToolbar({
  view,
  onViewChange,
  search,
  onSearchChange,
  status,
  onStatusChange,
  date,
  onDateChange,
  newCount = 0,
}: Props) {
  return (
    <div className="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm lg:flex-row lg:items-end lg:justify-between">
      <div>
        <div className="flex items-center gap-2">
          <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">Schedule Management</h1>
          {newCount > 0 && (
            <span className="rounded-full bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white">
              {newCount} new
            </span>
          )}
        </div>
        <p className="mt-1 text-sm text-slate-500">Manage accepted bookings and your calendar</p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="inline-flex rounded-lg bg-slate-100 p-1" role="group" aria-label="View mode">
          {(['daily', 'weekly'] as const).map((v) => (
            <button
              key={v}
              type="button"
              onClick={() => onViewChange(v)}
              className={`rounded-md px-3 py-1.5 text-sm font-medium capitalize ${
                view === v ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'
              }`}
            >
              {v}
            </button>
          ))}
        </div>

        <input
          type="date"
          value={date}
          onChange={(e) => onDateChange(e.target.value)}
          className="rounded-lg border border-slate-200 px-3 py-2 text-sm"
        />

        <select
          value={status}
          onChange={(e) => onStatusChange(e.target.value as '' | ScheduleStatus)}
          className="rounded-lg border border-slate-200 px-3 py-2 text-sm"
        >
          {STATUS_FILTER_OPTIONS.map((o) => (
            <option key={o.value || 'all'} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>

        <input
          type="search"
          placeholder="Search customer, service…"
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          className="min-w-[200px] flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm lg:max-w-xs"
        />
      </div>
    </div>
  );
}
