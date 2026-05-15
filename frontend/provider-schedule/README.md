# Provider Schedule Management (React)

Drop-in feature module for the Provider Dashboard. Copy `src/` into your Vite + React app (e.g. `src/features/provider-schedule/`).

## Install peer dependencies

```bash
npm install @tanstack/react-query date-fns laravel-echo pusher-js
```

## Environment (`.env` in your React app)

```env
VITE_API_URL=http://127.0.0.1:8000/api/v1
VITE_REVERB_APP_KEY=your-reverb-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

## Wire into dashboard router

```tsx
import { ProviderSchedulePage } from '@/features/provider-schedule/pages/ProviderSchedulePage';

{ path: '/provider/schedule', element: <ProviderSchedulePage /> }
```

## Realtime (Laravel Echo + Reverb)

Subscribe on the provider schedule page:

```ts
Echo.private(`provider.${providerId}`).listen('.schedule.updated', (e) => {
  queryClient.invalidateQueries({ queryKey: ['provider-schedule'] });
});
```

Bearer token: pass Sanctum token when initializing Echo auth (same as chat).
