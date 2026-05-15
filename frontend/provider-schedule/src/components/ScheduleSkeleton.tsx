export function ScheduleSkeleton() {
  return (
    <div className="space-y-4 animate-pulse" aria-busy="true" aria-label="Loading schedule">
      {[1, 2, 3].map((i) => (
        <div key={i} className="h-32 rounded-2xl bg-slate-200/80 shadow-sm" />
      ))}
    </div>
  );
}
