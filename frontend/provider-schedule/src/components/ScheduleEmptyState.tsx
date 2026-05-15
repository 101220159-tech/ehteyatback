export function ScheduleEmptyState({ view }: { view: 'daily' | 'weekly' }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
      <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">
        📅
      </div>
      <h3 className="text-lg font-semibold text-slate-800">No bookings scheduled</h3>
      <p className="mt-2 max-w-sm text-sm text-slate-500">
        {view === 'weekly'
          ? 'Nothing on the calendar for this week. Accepted bookings appear here automatically.'
          : 'No appointments for this day. When you accept a booking, it will show up here instantly.'}
      </p>
    </div>
  );
}
