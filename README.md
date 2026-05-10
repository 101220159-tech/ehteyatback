# NexVex — API

NexVex backend: Laravel API with Sanctum token authentication, custom RBAC (roles & permissions), and versioned JSON endpoints under `/api/v1`.

> **Note:** This repo uses **Laravel Framework 13** (`laravel/framework` ^13 per `composer.json`). Middleware is registered in `bootstrap/app.php` (there is no `app/Http/Kernel.php`).

## Requirements

- PHP 8.3+
- Composer
- MySQL 8+ (recommended for distance search) or SQLite (distance sort / haversine skipped automatically)

## Setup

1. Copy environment file and generate key:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

2. Configure `.env`: set `DB_*`, `APP_URL`, `FRONTEND_URL`, and mail settings for verification / password reset emails.

3. Migrate and seed:

   ```bash
   php artisan migrate:fresh --seed
   ```

4. Default admin (after seeding):

   - Email: `admin@sp-platform.test`
   - Password: `password`

5. Run the app:

   ```bash
   php artisan serve
   ```

6. Queue worker (for jobs such as email / notifications):

   ```bash
   php artisan queue:work
   ```

## API base URL

- Health: `GET /api/health`
- Versioned API: `/api/v1/...`

Send `Authorization: Bearer {token}` and `Accept: application/json` for authenticated routes.

## Payments

The API does **not** process card, wallet, or gateway payments. Bookings include `total_amount` as an agreed price reference; money is handled **offline** between customer and provider. Provider “earnings” and admin dashboard revenue use **completed bookings**’ `total_amount` sums only.

## RBAC

Roles: `super_admin`, `admin`, `provider`, `customer`. Permissions are stored in `permissions` and attached to roles; users can receive overrides via `user_permissions` (allow/deny with optional expiry).

## Maintenance commands

- `php artisan bookings:check-status` — mark overdue confirmed bookings as no-show
- `php artisan bookings:send-reminders` — email, push (FCM), and in-app reminders for the ~24h window (scheduled hourly)
- `php artisan providers:update-ratings` — refresh `rating_avg` / `total_reviews` from reviews
- `php artisan clean:old-notifications` — remove in-app notifications older than 30 days

## Schema note

The `services` table includes a **nullable** `provider_id` so platform catalog services (`provider_id` null) and provider-owned listings can coexist, matching the `Provider hasMany Service` relationship.

## Email verification & password reset

The `User` model does **not** use Laravel’s `Notifiable` trait, because the in-app table is named `notifications`. Verification and reset links are sent via `Mail::raw` using signed URLs (`api.v1.verification.verify`). Replace with Mailables or a provider SDK in production.

## Tests

```bash
php artisan test
```
