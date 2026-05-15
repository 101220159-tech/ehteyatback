import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';

/**
 * Subscribe to provider schedule updates via Laravel Echo (Reverb).
 * Requires Echo instance configured with Sanctum bearer auth (same as chat).
 */
export function useScheduleRealtime(
  echo: { private: (ch: string) => { listen: (ev: string, cb: (p: unknown) => void) => void } } | null,
  providerId: string | undefined
) {
  const qc = useQueryClient();

  useEffect(() => {
    if (!echo || !providerId) return;

    const channel = echo.private(`provider.${providerId}`);
    channel.listen('.schedule.updated', () => {
      qc.invalidateQueries({ queryKey: ['provider-schedule'] });
    });

    return () => {
      // Echo.leave(`private-provider.${providerId}`) when your Echo setup exposes leave
    };
  }, [echo, providerId, qc]);
}
