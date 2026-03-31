# Frontend integration: auth, roles, and permissions

This backend uses **Laravel Sanctum** and a **single role per user** (`users.role_id` → `roles`). Permissions come from the role (`role_permissions`) plus optional direct grants (`user_permissions`).

## CORS

- Configure `FRONTEND_URL` (default `http://localhost:3000` in `.env.example`).
- Optional: `CORS_ALLOWED_ORIGINS` as comma-separated list; if empty, only `FRONTEND_URL` is allowed.
- `CORS_SUPPORTS_CREDENTIALS` defaults to `true` for cookie/session flows with Sanctum.

## Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/v1/auth/login` | No | Returns `token` + `user` |
| GET | `/api/v1/auth/me` | Bearer token | Current user as `UserResource` |
| GET | `/api/v1/auth/permissions` | Bearer token | `{ "role_name", "permissions" }` only |
| GET | `/api/v1/test/permissions` | Bearer token | Same + `example_checks` booleans for QA |

## `UserResource` shape (login / me / profile)

When the authenticated user is **viewing themselves**, or an **admin/super_admin** is viewing a user in the admin API:

- `role_name`: `customer` | `provider` | `admin` | `super_admin`
- `permissions`: sorted array of permission name strings (role + direct)

When a user appears **nested** (e.g. booking `client`), **other** callers do **not** receive `role_name` or `permissions` (privacy).

## Dashboard behaviour (React)

1. **Layout / shell**: branch on `user.role_name` (or `role.name` inside nested `role` when loaded).
2. **Forms / buttons**: use `user.permissions.includes('permission_name')`.

Server routes still enforce `role:` and `permission:` middleware; the JSON is for UX only.

## Seeded permission names (high level)

Re-run `php artisan db:seed --class=RolePermissionSeeder` after pulling changes so new keys exist in DB.

- **Customer**: `create_bookings`, `view_own_bookings`, `cancel_own_bookings`, `create_reviews`, `edit_own_reviews`, `write_reviews`, `message_providers`, `view_provider_profiles`, `manage_own_profile`, `manage_own_addresses`, `send_messages`, `view_conversations`, …
- **Provider**: `manage_services`, `manage_own_bookings`, `manage_portfolio`, `view_own_reviews`, `respond_to_reviews`, `manage_own_availability`, `view_own_earnings`, `send_messages`, …
- **Admin / super_admin**: full set including `view_admin_dashboard`, `manage_users`, `verify_providers`, `manage_services_catalog`, `manage_zones`, `view_all_bookings`, `view_payments`, `manage_roles`, `view_reports`, …

Exact lists: `database/seeders/RolePermissionSeeder.php`.

## PHP helpers (server-side)

`App\Models\User` (via `HasRolesAndPermissions`):

- `hasRole(string|array $roles)`
- `hasPermission(string|array $permissions)`
- `effectivePermissionNames(): array`
- `$user->role_name` (accessor)
- `loadForApiSerialization()` before returning `UserResource` from auth/admin actions.

## Scribe (optional)

This repo ships this markdown doc instead of bundled Scribe. To add OpenAPI/HTML docs later: `composer require --dev knuckleswtf/scribe` then `php artisan scribe:generate`.
