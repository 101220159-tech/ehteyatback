# Frontend ↔ Backend API route map

Canonical copy for the **SP-Back** repo. The React app may live elsewhere (e.g. `C:\Users\HP\frontend`); keep this file in sync with the frontend’s `FRONTEND_BACKEND_API_ROUTES.md` when routes change.

## API surface

- **Prefix:** `/api/v1` (see `routes/api.php`, Laravel `bootstrap/app.php` uses `api` prefix).
- **Health:** `GET /api/health`

## Wiring

| App | Setting |
|-----|---------|
| Laravel `.env` | `FRONTEND_URL=http://localhost:5173` (Vite default) for CORS / Sanctum. |
| Frontend `.env` | `VITE_API_URL=/api/v1`, `VITE_PROXY_TARGET=http://127.0.0.1:8000` |

## Page → route matrix

See **`FRONTEND_BACKEND_API_ROUTES.md`** in the frontend repository for the full tables (same filename at the frontend project root):

- Public & auth routes  
- Customer `/customer/*` → `GET/POST /customer/...`  
- Provider `/provider/*` → `GET/POST /provider/...`  
- Admin `/admin/*` → `GET/POST /admin/...`  
- Chatbot, transport aliases (`/route/calculate`, `/taxi/book`, …)  
- Optional admin endpoints the UI may call that are not in `routes/api.php` yet  

If the frontend is only on your machine, open the copy at:

`C:\Users\HP\frontend\FRONTEND_BACKEND_API_ROUTES.md`
