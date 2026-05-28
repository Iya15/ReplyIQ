import { Skeleton } from '@/components/ui/skeleton';
import type { ReactNode } from 'react';

interface KpiCardProps {
  label:       string;
  value:       string | number | null | undefined;
  sub?:        string;
  icon?:       ReactNode;
  isLoading?:  boolean;
  highlight?:  'good' | 'warn' | 'bad' | null;
}

export function KpiCard({ label, value, sub, icon, isLoading, highlight }: KpiCardProps) {
  const highlightClass =
    highlight === 'bad'  ? 'text-red-600'   :
    highlight === 'warn' ? 'text-amber-600' :
    highlight === 'good' ? 'text-green-600' :
    'text-foreground';

  return (
    <div className="rounded-xl border bg-card p-5 flex flex-col gap-2">
      <div className="flex items-center justify-between">
        <span className="text-sm text-muted-foreground font-medium">{label}</span>
        {icon && <span className="text-muted-foreground/60">{icon}</span>}
      </div>
      {isLoading ? (
        <Skeleton className="h-8 w-24" />
      ) : (
        <div className={`text-2xl font-bold tracking-tight ${highlightClass}`}>
          {value ?? '—'}
        </div>
      )}
      {sub && !isLoading && (
        <p className="text-xs text-muted-foreground">{sub}</p>
      )}
    </div>
  );
}
