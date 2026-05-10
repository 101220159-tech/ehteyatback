# SP Capstone — Complete Frontend Documentation

> **Base URL:** `http://127.0.0.1:8000/api/v1`
> **Auth:** All protected endpoints require `Authorization: Bearer {token}` header.
> **Format:** All requests and responses are `application/json` unless uploading files (`multipart/form-data`).
> **IDs:** Every resource ID is a UUID string (e.g. `"a1b2c3d4-..."`).

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Authentication Flows](#2-authentication-flows)
3. [Super Admin Platform](#3-super-admin-platform)
4. [Provider Platform](#4-provider-platform)
5. [Customer Platform](#5-customer-platform)
6. [Shared Concepts](#6-shared-concepts)

---

## 1. System Overview

### Who uses the system?

| Role | Description |
|------|-------------|
| `super_admin` | Full control — manages everything |
| `admin` | Same permissions as super_admin (can be scoped later) |
| `provider` | Service professional — manages their own profile, bookings, availability |
| `customer` | End user — searches, books, reviews providers |

### How authentication works

- Registration and login return a **Sanctum Bearer token**.
- Store the token in `localStorage` or `sessionStorage`.
- Send it on every protected request: `Authorization: Bearer <token>`.
- On app load, call `GET /auth/me` to restore session and determine which dashboard to show based on `role_name`.

### Role-based routing strategy (frontend)

```
Login response → check role_name:
  "super_admin" or "admin" → redirect to /admin/dashboard
  "provider"               → redirect to /provider/dashboard
  "customer"               → redirect to /customer/dashboard
```

---

## 2. Authentication Flows

### 2.1 Register (Email)

**POST** `/auth/register`

**Payload (customer):**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "Secret@123",
  "password_confirmation": "Secret@123",
  "role": "customer",
  "phone": "03123456"
}
```

**Payload (provider) — `provider_phone` is required:**
```json
{
  "name": "Ali Hassan",
  "email": "ali@example.com",
  "password": "Secret@123",
  "password_confirmation": "Secret@123",
  "role": "provider",
  "provider_phone": "70123456"
}
```
- `role`: `"customer"` or `"provider"`. Omit for default customer.
- `provider_phone`: Required when `role = "provider"`. Must be a valid Lebanese mobile number (e.g. `70123456`, `03123456`).

**Response `201`:**
```json
{
  "message": "Registered successfully. Please verify your email.",
  "token": "1|abc...",
  "user": { "id": "uuid", "name": "John Doe", "email": "...", "role_name": "customer" }
}
```

**UI Notes:**
- Show "Check your email for a verification link" banner after register.
- Providers cannot access the platform until email is verified.

---

### 2.2 Login (Email)

**POST** `/auth/login`

**Payload:**
```json
{
  "email": "john@example.com",
  "password": "Secret@123"
}
```

**Response `200`:**
```json
{
  "token": "2|xyz...",
  "user": {
    "id": "uuid",
    "name": "John Doe",
    "role_name": "customer",
    "permissions": ["create_bookings", "cancel_own_bookings", "..."]
  }
}
```

---

### 2.3 Google OAuth (Customers only)

**Flow:**
1. Show "Sign in with Google" button.
2. Redirect browser to `GET /auth/google` — this redirects to Google.
3. Google redirects back to `GET /auth/google/callback`.
4. Backend returns token + user. Frontend reads the token from the response.

> For SPA (React/Vue): Use Google Identity popup, get the OAuth `code`, then send it to `/auth/google/callback`. The backend exchanges it and returns a token.

---

### 2.4 Logout

**POST** `/auth/logout` *(requires token)*

**Response:**
```json
{ "message": "Logged out." }
```

---

### 2.5 Forgot / Reset Password

**POST** `/auth/forgot-password`
```json
{ "email": "john@example.com" }
```

**POST** `/auth/reset-password`
```json
{
  "token": "token-from-email-link",
  "email": "john@example.com",
  "password": "NewPass@123",
  "password_confirmation": "NewPass@123"
}
```

---

### 2.6 Get Current User

**GET** `/auth/me` *(requires token)*

Returns full user object including `role_name` and `permissions[]`. Call this on every app load.

---

---

## 3. Super Admin Platform

### What the Admin Sees

The admin dashboard is a **management console** with a sidebar containing:
- Dashboard (stats)
- Users
- Providers
- Services & Categories
- Zones & Areas
- Payments & Subscriptions
- Bookings
- Roles & Permissions
- Notifications

---

### 3.1 Dashboard Stats

**GET** `/admin/dashboard/stats`

**Response:**
```json
{
  "total_users": 120,
  "total_providers": 34,
  "active_providers": 20,
  "total_bookings": 450,
  "pending_bookings": 12,
  "total_revenue": 15400.00
}
```

**UI:** Show KPI cards (total users, active providers, bookings today, revenue).

---

### 3.2 User Management

#### List Users
**GET** `/admin/users?page=1`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Jane",
      "email": "jane@example.com",
      "role_name": "customer",
      "phone": "03111111",
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "total": 120 }
}
```

#### Create User (admin creates any user)
**POST** `/admin/users`
```json
{
  "name": "New User",
  "email": "newuser@example.com",
  "password": "Pass@1234",
  "password_confirmation": "Pass@1234",
  "role_id": "<uuid from GET /admin/roles>",
  "phone": "03123456"
}
```

#### Show User
**GET** `/admin/users/{id}`

#### Update User
**PUT** `/admin/users/{id}`

All fields are optional (`sometimes`). Only send what you want to change.
```json
{
  "name": "Updated Name",
  "email": "new@example.com",
  "password": "NewPass@123",
  "password_confirmation": "NewPass@123",
  "role_id": "<uuid from GET /admin/roles>",
  "phone": "03999999"
}
```

#### Delete User
**DELETE** `/admin/users/{id}`

#### Assign Role to User
**POST** `/admin/users/{id}/assign-role`
```json
{ "role_id": "<uuid from GET /admin/roles>" }
```

#### Grant Direct Permissions to User
**POST** `/admin/users/{id}/grant-permission`
```json
{
  "permission_ids": [
    "<uuid from GET /admin/permissions>",
    "<uuid from GET /admin/permissions>"
  ]
}
```
- `permission_ids` (required): Array of one or more permission UUIDs. Grants all of them in a single request.

**UI Flow:**
- Table with search, filter by role, pagination.
- Row actions: View, Edit, Delete, Assign Role.
- Modal for assigning roles/permissions.

---

### 3.3 Role & Permission Management

#### List Roles
**GET** `/admin/roles`

**Response:**
```json
{
  "data": [
    { "id": "uuid", "name": "provider", "description": "Service provider", "permissions": [...] }
  ]
}
```

#### Create Role
**POST** `/admin/roles`
```json
{
  "name": "moderator",
  "description": "Content moderator",
  "permission_ids": [
    "<uuid from GET /admin/permissions>",
    "<uuid from GET /admin/permissions>"
  ]
}
```
- `permission_ids`: Optional array of permission **UUIDs** to assign immediately.

#### Update Role
**PUT** `/admin/roles/{id}`
```json
{
  "name": "moderator",
  "description": "Updated description",
  "permission_ids": [
    "<uuid from GET /admin/permissions>",
    "<uuid from GET /admin/permissions>"
  ]
}
```
- `permission_ids`: If included, **replaces** the full set of permissions on the role.

#### Delete Role
**DELETE** `/admin/roles/{id}`

#### List All Permissions
**GET** `/admin/permissions`

#### Create Permission
**POST** `/admin/permissions`
```json
{ "name": "view_reports", "description": "Can view analytics" }
```

**UI Flow:**
- Role list with permission chips per role.
- Click role → see all assigned permissions.
- Checkbox matrix to assign/revoke permissions from a role.

---

### 3.4 Provider Management

#### Create Provider (Admin)
**POST** `/admin/providers`
```json
{
  "name": "Ali Hassan",
  "email": "ali@example.com",
  "password": "Pass@1234",
  "password_confirmation": "Pass@1234",
  "phone": "70123456",
  "latitude": 33.8938,
  "longitude": 35.5018,
  "address": "Hamra, Beirut",
  "bio": "Experienced plumber with 5 years.",
  "experience_years": 5,
  "is_verified": true
}
```
- `name`, `email`, `password`, `password_confirmation` — required.
- `is_verified` (optional, boolean): if `true`, provider is verified immediately and their account is activated. Default: `false`.
- Account email is pre-verified by the admin — provider can log in immediately.

**Response `201`:**
```json
{
  "success": true,
  "message": "Provider created successfully.",
  "data": { "id": "uuid", "user": { "name": "Ali Hassan" }, "is_verified": true }
}
```

#### List Providers
**GET** `/admin/providers?page=1`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "user": { "name": "Ali Hassan", "email": "ali@test.com" },
      "is_verified": false,
      "is_vip": false,
      "is_active": true,
      "allow_chat": false,
      "subscription_expires_at": "2026-07-01T00:00:00Z",
      "zones": [{ "id": "uuid", "name": "Beirut West" }],
      "rating_avg": 4.5,
      "experience_years": 3
    }
  ]
}
```

#### Show Provider Detail
**GET** `/admin/providers/{id}`

Returns full provider with certifications, documents, services, availability, projects.

#### Verify / Unverify Provider
**POST** `/admin/providers/{id}/verify`
```json
{
  "is_verified": true,
  "verification_notes": "Documents look good."
}
```
- `is_verified` (required): `true` to verify, `false` to unverify.
- `verification_notes` (optional): Internal note, accepted but not stored.
- When verified: assigns `provider` role, marks email as verified, sets status to `active`.
- When unverified: reverts role to `customer`, clears email verification.

#### Approve Provider
**POST** `/admin/providers/{id}/approve`

No payload. Same as `verify` with `is_verified: true` — assigns provider role and activates.

#### Toggle Chat Permission (Admin only — provider cannot change this)
**PUT** `/admin/providers/{id}/chat`
```json
{ "allow_chat": true }
```

#### Admin chat / “groups” (moderation)
Same data as customer–provider chats; aliases exist so different frontends do not 404.

- **GET** `/admin/chats` — paginated list of all chats.
- **POST** `/admin/chats` — open or create a thread: `{ "customer_id": "<user-uuid>", "provider_id": "<provider-uuid>" }` (201 if created, 200 if already existed).
- **GET** `/admin/chats/{id}/messages` — messages in a chat.

Compat paths (same handlers as above):

- **GET|POST** `/admin/chat/groups`
- **GET|POST** `/admin/chats/groups`

#### Toggle VIP Status
**PUT** `/admin/providers/{id}/vip`
```json
{ "is_vip": true }
```

#### Assign Provider to Zones
**POST** `/admin/providers/{id}/assign-zones`
```json
{
  "zone_ids": ["zone-uuid-1", "zone-uuid-2"]
}
```

**UI Flow:**
- Provider table with columns: Name, Verified, VIP, Active, Subscription expiry, Zones, Actions.
- Provider detail page showing all info tabs: Profile, Certifications, Documents, Services, Availability, Projects.
- Toggle switches for `allow_chat`, `is_vip`.
- Multi-select zone assignment dropdown.

---

### 3.5 Zone & Area Management

> **Concept:** A **Zone** is a large region (e.g. "Beirut West"). Each zone has multiple **Areas** (e.g. "Hamra", "Rawshe", "Verdun"). Areas have polygon coordinates drawn on a Google Map. Providers are assigned to one or more zones by the admin.

#### List All Zones (with areas)
**GET** `/admin/zones`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Beirut West",
      "description": "Western Beirut zone",
      "areas": [
        {
          "id": "uuid",
          "name": "Hamra",
          "coordinates": [
            { "lat": 33.8938, "lng": 35.5018 },
            { "lat": 33.8950, "lng": 35.5030 },
            { "lat": 33.8920, "lng": 35.5040 }
          ]
        }
      ]
    }
  ]
}
```

#### Create Zone
**POST** `/admin/zones`
```json
{ "name": "Beirut East", "description": "Eastern Beirut districts" }
```

#### Update Zone
**PUT** `/admin/zones/{id}`
```json
{ "name": "Beirut East Updated" }
```

#### Delete Zone
**DELETE** `/admin/zones/{id}`

#### List Areas in a Zone
**GET** `/admin/zones/{zoneId}/areas`

#### Create Area (with Google Maps polygon)
**POST** `/admin/zones/{zoneId}/areas`
```json
{
  "name": "Hamra",
  "coordinates": [
    { "lat": 33.8938, "lng": 35.5018 },
    { "lat": 33.8950, "lng": 35.5030 },
    { "lat": 33.8920, "lng": 35.5040 },
    { "lat": 33.8938, "lng": 35.5018 }
  ]
}
```
> `coordinates` must have at least 3 points. The frontend uses **Google Maps Drawing API** to let the admin draw the polygon, then sends the resulting array of `{lat, lng}` points.

#### Update Area
**PUT** `/admin/zones/{zoneId}/areas/{id}`
```json
{
  "name": "Hamra District",
  "coordinates": [ ... ]
}
```

#### Delete Area
**DELETE** `/admin/zones/{zoneId}/areas/{id}`

#### List Providers in a Zone
**GET** `/admin/zones/{id}/providers`

#### Assign Providers to Zone
**POST** `/admin/zones/{id}/assign-providers`
```json
{ "provider_ids": ["uuid1", "uuid2"] }
```

#### Remove Provider from Zone
**DELETE** `/admin/zones/{id}/providers/{providerId}`

**UI Flow:**
1. Zone list page — table of zones, each row expandable to show areas.
2. Click zone → Zone Detail page with:
   - Google Map showing all area polygons drawn.
   - "+ Add Area" button that opens drawing mode on the map.
   - Provider assignment section: multi-select dropdown of all providers.
3. When admin draws a polygon on map, coordinates are auto-captured and submitted with the area name.

---

### 3.6 Service Category & Service Management

#### List Categories
**GET** `/admin/categories`

**Response:**
```json
{
  "data": [
    { "id": "uuid", "name": "Plumbing", "description": "...", "icon_url": null }
  ]
}
```

#### Create Category
**POST** `/admin/categories`
```json
{ "name": "Painting", "description": "Interior and exterior painting" }
```

#### Update Category
**PUT** `/admin/categories/{id}`
```json
{ "name": "Painting & Decorating" }
```

#### Delete Category
**DELETE** `/admin/categories/{id}`

#### List Services
**GET** `/admin/services`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "category_id": "uuid",
      "name": "Pipe Repair",
      "description": "Fix leaking pipes",
      "category": { "id": "uuid", "name": "Plumbing" }
    }
  ]
}
```

#### Create Service
**POST** `/admin/services`
```json
{
  "category_id": "uuid",
  "name": "Wall Painting",
  "description": "Interior wall painting per room"
}
```
> The price is **not** set here — each provider sets their own price when they add the service to their offerings (see section 4.6).

#### Update Service
**PUT** `/admin/services/{id}`
```json
{
  "name": "Wall Painting",
  "description": "Updated description",
  "category_id": "uuid"
}
```
All fields are optional on update (`sometimes`).

#### Delete Service
**DELETE** `/admin/services/{id}`

**UI Flow:**
- Two-panel layout: category list on left, services of selected category on right.
- Inline editing for category names.
- Service form with category dropdown, name, description, base price.

---

### 3.7 Payment & Subscription Management

> The admin manually records payments received from providers. Recording a **subscription** payment activates the provider and sets expiry. Recording a **vip_upgrade** payment sets the VIP flag.

#### List Payments
**GET** `/admin/payments?provider_id=uuid&status=completed&type=subscription`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "provider": { "user": { "name": "Ali Hassan" } },
      "amount": 100.00,
      "type": "subscription",
      "status": "completed",
      "paid_at": "2026-04-01T10:00:00Z",
      "expires_at": "2026-07-01T10:00:00Z"
    }
  ]
}
```

#### Record a Payment
**POST** `/admin/payments`
```json
{
  "provider_id": "uuid",
  "amount": 100.00,
  "type": "subscription",
  "description": "Q2 2026 subscription",
  "paid_at": "2026-04-17T10:00:00Z",
  "expires_at": "2026-07-17T10:00:00Z"
}
```
- `type`: `"subscription"` or `"vip_upgrade"`
- Recording a subscription auto-sets `provider.is_active = true` and updates `subscription_expires_at`.
- Recording `vip_upgrade` auto-sets `provider.is_vip = true`.

#### Show Payment
**GET** `/admin/payments/{id}`

**UI Flow:**
- Payments table with filter by provider, type, status.
- "Record Payment" button opens modal with form.
- Provider subscription status badge (Active / Expired) shown next to provider name throughout admin.

---

### 3.8 Booking Management (Admin View)

#### List All Bookings
**GET** `/admin/bookings?page=1`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "customer": { "name": "Sara" },
      "provider": { "user": { "name": "Ali" } },
      "service": { "name": "Pipe Repair" },
      "scheduled_at": "2026-05-01T10:00:00Z",
      "status": "accepted"
    }
  ]
}
```

**UI:** Read-only table. Admin can filter by status, date range, provider. No status changes from admin side (that is the provider's job).

---

### 3.9 Notification Broadcasting

#### List Notifications (admin inbox)
**GET** `/admin/notifications`

#### Send Notification to Specific Users
**POST** `/admin/notifications/send`
```json
{
  "user_ids": [1, 5, 12],
  "type": "system",
  "title": "Maintenance tonight",
  "content": "The platform will be down from 2–3 AM.",
  "data": { "action": "none" }
}
```
- `user_ids` (required): Array of user IDs to notify.
- `type` (required): Notification type string (e.g. `"system"`, `"booking_update"`).
- `title` (required): Short notification title.
- `content` (required): Full notification body text.
- `data` (optional): Extra JSON payload for the notification.

---

---

## 4. Provider Platform

### What the Provider Sees

After login, the provider sees a sidebar with:
- Dashboard
- My Status (active/inactive toggle)
- My Profile
- Certifications
- Documents
- Services I Offer
- My Availability
- My Portfolio
- Bookings
- Reviews
- Chat
- Earnings

> **Important:** Provider cannot access any dashboard features if their subscription has expired. Show a full-page "Subscription Expired — Contact Admin" screen when `subscription_active = false`.

---

### 4.1 Provider Dashboard

**GET** `/provider/dashboard`

**Response:**
```json
{
  "total_bookings": 45,
  "pending_bookings": 3,
  "completed_bookings": 40,
  "rating_avg": 4.7,
  "earnings_this_month": 1200.00
}
```

---

### 4.2 Provider Status Toggle

> Provider can go Active or Inactive. **Cannot go Active if subscription is expired.**

#### Get Current Status
**GET** `/provider/status`

**Response:**
```json
{
  "is_active": false,
  "is_busy": false,
  "is_vip": true,
  "subscription_expires_at": "2026-07-17T10:00:00Z",
  "subscription_active": true
}
```

#### Toggle Status
**PUT** `/provider/status`
```json
{ "is_active": true }
```

**Error if subscription expired:**
```json
{
  "message": "Your subscription has expired. Please renew to go active.",
  "errors": { "subscription": ["Subscription expired."] }
}
```

**UI:** Large toggle switch at top of dashboard. Show subscription expiry date. If expired → show warning banner and disable toggle.

---

### 4.3 Provider Profile

#### Get Profile
**GET** `/provider/profile`

#### Update Profile
**PUT** `/provider/profile`

All fields are optional. Send only what you want to change.
```json
{
  "name": "Ali Hassan",
  "email": "ali@example.com",
  "phone": "70123456",
  "latitude": 33.8938,
  "longitude": 35.5018,
  "bio": "Experienced plumber with 5 years in Beirut.",
  "experience_years": 5
}
```
- `name`, `email`, `phone`, `latitude`, `longitude`: Update the user account fields.
- `bio`, `experience_years`: Update the provider profile fields.
- `phone` must be a valid Lebanese mobile number.

#### Update Location
**PUT** `/provider/profile/location`
```json
{
  "latitude": 33.8938,
  "longitude": 35.5018,
  "address": "Hamra, Beirut"
}
```

**UI:** Profile form with bio textarea, experience years. Map picker for location.

---

### 4.4 Certifications

#### List
**GET** `/provider/certifications`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "title": "Master Electrician License",
      "issuer": "Lebanese Ministry of Labor",
      "issued_at": "2020-06-01",
      "expires_at": "2026-06-01",
      "file_url": "http://.../certifications/file.pdf"
    }
  ]
}
```

#### Add Certification
**POST** `/provider/certifications` *(multipart/form-data)*

| Field | Type | Required |
|-------|------|----------|
| title | string | yes |
| issuer | string | no |
| issued_at | date (YYYY-MM-DD) | no |
| expires_at | date (YYYY-MM-DD) | no |
| file | PDF/image (max 5MB) | no |

#### Update Certification
**PUT** `/provider/certifications/{id}` *(multipart/form-data)*

Same fields as store, all optional.

#### Delete
**DELETE** `/provider/certifications/{id}`

**UI:** Card list of certifications. Each card shows title, issuer, expiry date, download link. "+ Add" button opens modal form with file upload.

---

### 4.5 Official Documents

#### List
**GET** `/provider/documents`

#### Upload Document
**POST** `/provider/documents` *(multipart/form-data)*

| Field | Type | Required | Values |
|-------|------|----------|--------|
| type | string | yes | `id_card`, `trade_license`, `insurance`, `certificate`, `other` |
| title | string | yes | — |
| file | PDF/image (max 10MB) | yes | — |

#### Delete
**DELETE** `/provider/documents/{id}`

**UI:** Document list grouped by type. Upload button with type selector and file picker.

---

### 4.6 Services I Offer

> Provider selects from the catalog services and sets their own price.

#### List Own Services
**GET** `/provider/services`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "service": { "id": "uuid", "name": "Pipe Repair", "category": { "name": "Plumbing" } },
      "price": 65.00
    }
  ]
}
```

#### Add a Service
**POST** `/provider/services`
```json
{
  "service_id": "uuid",
  "price": 65.00
}
```

#### Update Price
**PUT** `/provider/services/{id}`
```json
{ "price": 70.00 }
```

#### Remove Service
**DELETE** `/provider/services/{id}`

**UI:** Two-column layout. Left: searchable catalog of all services grouped by category. Right: provider's active services with price editing. Drag from left to right to add.

---

### 4.7 Availability Schedule

> Provider sets their weekly schedule. Each slot is a day + start time + end time.

#### List Availability
**GET** `/provider/availability`

**Response:**
```json
{
  "data": [
    { "id": "uuid", "day_of_week": "monday", "start_time": "09:00:00", "end_time": "17:00:00", "is_available": true },
    { "id": "uuid", "day_of_week": "wednesday", "start_time": "10:00:00", "end_time": "15:00:00", "is_available": true }
  ]
}
```
> `day_of_week` is stored as a **string** exactly as submitted. Use consistent values across your app (e.g. always `"monday"`, `"tuesday"`, ... or always `"1"`, `"2"`, ...).

#### Add Slot
**POST** `/provider/availability`
```json
{
  "day_of_week": "monday",
  "start_time": "09:00",
  "end_time": "17:00",
  "is_available": true
}
```
- `day_of_week` (required, **string**): Day name or number as a string. Use `"0"`–`"6"` (0=Sunday) or full name like `"monday"`, `"tuesday"`, etc. — whatever the frontend stores, the backend saves it as-is.
- `start_time` / `end_time`: Time string in `HH:MM` format.
- `is_available`: Defaults to `true` if omitted.

#### Update Slot
**PUT** `/provider/availability/{id}`
```json
{
  "day_of_week": "monday",
  "start_time": "10:00",
  "end_time": "18:00",
  "is_available": true
}
```
All fields are required (this is a full update, not partial).

#### Delete Slot
**DELETE** `/provider/availability/{id}`

**UI:** Weekly calendar grid (Sun–Sat). Each day shows time slots as colored bars. Click day to add/edit slots with a time-range picker.

---

### 4.8 Portfolio / Projects

#### List Projects
**GET** `/provider/projects`

#### Create Project
**POST** `/provider/projects`
```json
{
  "title": "Kitchen Plumbing Renovation",
  "description": "Full kitchen plumbing overhaul for a 3-bedroom apartment."
}
```

#### Update Project
**PUT** `/provider/projects/{id}`

#### Delete Project
**DELETE** `/provider/projects/{id}`

#### Upload Images to Project
**POST** `/provider/projects/{id}/images` *(multipart/form-data)*

| Field | Type |
|-------|------|
| images[] | image files (jpg/png, max 5MB each) |

**UI:** Project cards with image gallery. "+ New Project" form. Image upload with drag-and-drop and preview.

---

### 4.9 Booking Management (Provider Side)

#### List Bookings
**GET** `/provider/bookings?status=pending`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "customer": { "name": "Sara", "phone": "03444444" },
      "service": { "name": "Pipe Repair" },
      "scheduled_at": "2026-05-01T10:00:00Z",
      "duration_minutes": 60,
      "status": "pending",
      "customer_address": "Hamra, Beirut",
      "customer_notes": "Leaking under sink",
      "latest_reschedule_request": null
    }
  ]
}
```

#### Show Booking Detail
**GET** `/provider/bookings/{id}`

Includes `reschedule_requests[]` and `review`.

#### Accept Booking
**PUT** `/provider/bookings/{id}/accept`

No payload. Sets status → `accepted`. Customer gets notified.

#### Reject Booking
**PUT** `/provider/bookings/{id}/reject`
```json
{ "reason": "I am not available that day." }
```
Sets status → `rejected`. Customer gets notified with reason.

#### Propose Reschedule
**POST** `/provider/bookings/{id}/reschedule`
```json
{
  "proposed_at": "2026-05-02T14:00:00",
  "message": "I can do it the next day at 2 PM instead."
}
```
Sets status → `reschedule_requested`. Customer gets notified.

#### Mark as Completed
**PUT** `/provider/bookings/{id}/complete`

No payload. Sets status → `completed`, clears `is_busy`. Customer gets notified and prompted for a review.

**UI — Booking Workflow:**
```
PENDING → [Accept] → ACCEPTED → (time arrives) → is_busy = true
        → [Reject]  → REJECTED
        → [Reschedule] → RESCHEDULE_REQUESTED → customer accepts → ACCEPTED
                                               → customer rejects → PENDING
ACCEPTED → [Mark Complete] → COMPLETED
```

- Bookings page: tabbed by status (Pending, Accepted, Completed, etc.).
- Pending cards: big Accept / Reject / Reschedule buttons.
- Accepted cards: "Mark as Complete" button.
- When status is `reschedule_requested`, show the proposed time and "Awaiting customer response" badge.

---

### 4.10 Reviews (Provider View)

**GET** `/provider/reviews`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "customer": { "name": "Sara" },
      "rating": 5,
      "comment": "Excellent service!",
      "created_at": "2026-04-10T12:00:00Z"
    }
  ]
}
```

**UI:** Read-only review list with star ratings. Show average rating prominently.

---

### 4.11 Chat

> Chat is only enabled if `provider.allow_chat = true` (set by admin).

#### List Conversations
**GET** `/provider/chats`

#### List Messages in a Chat
**GET** `/provider/chats/{id}/messages`

> **Pagination:** Messages are ordered oldest → newest. If you **omit** `page`, the API defaults to the **last** page (newest “window”). That avoids a refetch after sending returning only `page=1` (oldest chunk) and making the new bubble disappear briefly. Pass `page` explicitly when loading older history (e.g. `page=1` for the oldest segment, or previous pages when scrolling up).

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "sender_id": "uuid",
      "body": "Hi, when can you come?",
      "type": "text",
      "read_at": null,
      "created_at": "2026-04-17T09:00:00Z"
    }
  ]
}
```

#### Send Message
**POST** `/provider/chats/{id}/messages`
```json
{ "body": "I can come tomorrow at 10 AM." }
```

Headers (recommended):

- **`X-Socket-ID`**: Value from Laravel Echo / Pusher (`Echo.socketId()`). Lets the server exclude your WebSocket when broadcasting so you do **not** get the same `message.sent` event again a few seconds later (duplicate bubble).
- **`Idempotency-Key`**: Optional UUID (or unique string) per user action. If the client retries the same POST (double submit, React Strict Mode), the API returns the existing message with **200** instead of inserting a second row.

Backend also deduplicates: same user, same chat, **same body within ~10 seconds** → returns the first message (**200**), no second row / no second broadcast. Repeat the exact same text after a short pause if you need two identical lines.

Live broadcasts use **`ShouldBroadcastNow`** (no queue delay). The UI should still ignore Echo `message.sent` when `payload.sender_id === currentUser.id` if you ever see a stray duplicate.

#### Mark Message as Read
**PUT** `/provider/chats/{id}/messages/{msgId}/read`

#### Typing Indicator
**POST** `/provider/chats/{id}/typing`

**UI:** WhatsApp-style chat list on left, conversation on right. Show "Chat is disabled" if `allow_chat = false`.

---

### 4.12 Earnings

**GET** `/provider/earnings`

**Response:**
```json
{
  "total_earnings": 4500.00,
  "this_month": 1200.00,
  "completed_bookings": 45
}
```

---

---

## 5. Customer Platform

### What the Customer Sees

After login/signup, the customer lands on a **home screen** with:
- Category browser
- Search bar
- Nearby providers map
- My bookings
- Notifications

---

### 5.1 Browse Categories & Services

#### List All Categories (public — no auth needed)
**GET** `/categories`

**Response:**
```json
{
  "data": [
    { "id": "uuid", "name": "Plumbing", "icon_url": null },
    { "id": "uuid", "name": "Electrical", "icon_url": null }
  ]
}
```

#### Get Services in a Category (public)
**GET** `/categories/{id}/services`

**Response:**
```json
{
  "data": [
    { "id": "uuid", "name": "Pipe Repair", "base_price": 50.00 }
  ]
}
```

**UI:** Icon grid of categories on home screen. Tapping a category shows its services list. Tapping a service launches provider search pre-filtered for that service.

---

### 5.2 Search & Filter Providers

> **Core business logic:** Providers are filtered by zone/area matching the customer's location. Results are sorted: **VIP first → highest rating → most years of experience**.

#### Search Providers
**GET** `/customer/providers/search`

**Query params:**

| Param | Description | Example |
|-------|-------------|---------|
| `service_id` | Filter by specific service UUID | `?service_id=uuid` |
| `category_id` | Filter by category UUID | `?category_id=uuid` |
| `zone_id` | Filter by zone UUID (providers who serve that zone) | `?zone_id=uuid` |
| `keyword` | Text search in bio, name | `?keyword=plumber` |
| `min_rating` | Minimum rating | `?min_rating=4` |
| `latitude` | Customer's latitude for distance calc | `?latitude=33.89` |
| `longitude` | Customer's longitude | `?longitude=35.50` |
| `radius_km` | Max distance in km | `?radius_km=10` |

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "is_vip": true,
      "is_active": true,
      "is_busy": false,
      "rating_avg": 4.8,
      "experience_years": 6,
      "allow_chat": true,
      "zones": [{ "id": "uuid", "name": "Beirut West" }],
      "user": { "name": "Ali Hassan", "avatar_url": "..." },
      "services": [{ "name": "Pipe Repair", "price": 65.00 }],
      "distance_km": 1.4
    }
  ]
}
```

**UI Flow:**
1. Customer opens app → browser asks for location permission.
2. If granted: use coordinates to find matching zone → pass `zone_id` to search.
3. If denied: show zone picker manually.
4. Results list with VIP badge (gold crown), rating stars, experience label, distance.
5. Busy providers show "Currently Busy" badge — they appear in results but cannot be booked right now.

---

### 5.3 View Provider Public Profile

**GET** `/providers/{id}` *(public)*

**Response:**
```json
{
  "id": "uuid",
  "user": { "name": "Ali Hassan", "avatar_url": "..." },
  "bio": "5 years experience...",
  "rating_avg": 4.8,
  "total_reviews": 32,
  "experience_years": 5,
  "is_vip": true,
  "is_active": true,
  "is_busy": false,
  "allow_chat": true,
  "services": [...],
  "zones": [...],
  "projects": [
    { "title": "...", "images": [{ "image_url": "..." }] }
  ]
}
```

**UI:** Provider profile page with:
- Avatar, name, rating stars, VIP badge if applicable.
- Services offered with prices.
- Zones served.
- Portfolio image gallery.
- "Book Now" button (disabled if `is_busy = true`).
- "Chat" button (only shown if `allow_chat = true`).

---

### 5.4 Create a Booking

**POST** `/customer/bookings`

```json
{
  "provider_id": "uuid",
  "service_id": "uuid",
  "scheduled_at": "2026-05-01T10:00:00",
  "duration_minutes": 60,
  "customer_notes": "The leak is under the kitchen sink.",
  "customer_latitude": 33.8938,
  "customer_longitude": 35.5018,
  "customer_address": "Hamra, Beirut"
}
```

**Errors to handle:**

| HTTP | Reason |
|------|--------|
| `422` "Provider is not currently active." | Provider toggled inactive |
| `422` "Provider is not available at the requested time." | Outside availability schedule |
| `422` "This time slot is already taken." | Conflict with another accepted booking |

**Response `201`:**
```json
{
  "message": "Booking created. Awaiting provider confirmation.",
  "data": {
    "id": "uuid",
    "status": "pending",
    "scheduled_at": "2026-05-01T10:00:00Z"
  }
}
```

**UI Flow:**
1. Customer clicks "Book Now" on provider profile.
2. Step 1 — Select service from provider's offered services.
3. Step 2 — Pick date/time: show a calendar that only highlights the provider's available days. Within a selected day, show available time slots (cross-checked against the provider's existing accepted bookings).
4. Step 3 — Add notes + confirm location (prefilled from device GPS).
5. Submit → show "Booking Sent! Awaiting confirmation" screen.

---

### 5.5 My Bookings

#### List Bookings
**GET** `/customer/bookings?status=pending`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "provider": { "user": { "name": "Ali" } },
      "service": { "name": "Pipe Repair" },
      "scheduled_at": "2026-05-01T10:00:00Z",
      "status": "pending",
      "latest_reschedule_request": {
        "proposed_at": "2026-05-02T14:00:00Z",
        "message": "Can we do it the next day?",
        "status": "pending"
      }
    }
  ]
}
```

#### Show Booking Detail
**GET** `/customer/bookings/{id}`

Includes full booking + review + all reschedule_requests.

---

### 5.6 Cancel a Booking

**PUT** `/customer/bookings/{id}/cancel`

No payload. Only works when status is `pending` or `reschedule_requested`.

**Response:**
```json
{ "message": "Booking cancelled." }
```

---

### 5.7 Reschedule Workflow (Customer Side)

When the provider proposes a new time, the booking status becomes `reschedule_requested`. The `latest_reschedule_request` object will have `status: "pending"`.

**Show on UI:** A card/banner saying "Provider proposed a new time: May 2nd at 2:00 PM — [Accept] [Reject]"

#### Accept Reschedule
**PUT** `/customer/bookings/{id}/reschedule/accept`

No payload. Sets booking back to `accepted` with the new time.

#### Reject Reschedule
**PUT** `/customer/bookings/{id}/reschedule/reject`

No payload. Returns booking to `pending`.

---

### 5.8 Leave a Review

> Can only be done once per completed booking.

**POST** `/customer/reviews`
```json
{
  "provider_id": "<uuid of the provider>",
  "rating": 5,
  "comment": "Ali was professional and fixed the leak quickly!"
}
```
- `provider_id` (required): The UUID of the provider being reviewed.
- `rating` (required): Integer between 1 and 5.
- `comment` (optional): Text review, max 5000 characters.

**Update Review:**
**PUT** `/customer/reviews/{id}`
```json
{ "rating": 4, "comment": "Good but slightly late." }
```
Both fields are optional on update.

---

### 5.9 Chat with Provider

> Chat button only appears if `provider.allow_chat = true`.

#### Start or Get Existing Chat
**POST** `/customer/chats`
```json
{ "provider_id": "uuid" }
```

Returns existing chat if one already exists.

#### List All Chats
**GET** `/customer/chats`

#### List Messages
**GET** `/customer/chats/{id}/messages`

> Same pagination rule as provider messages: omit `page` to get the **latest** window by default; pass `page` when loading older history.

#### Send Message
**POST** `/customer/chats/{id}/messages`
```json
{ "body": "Hi, can you come on Saturday?" }
```

#### Mark Message as Read
**PUT** `/customer/chats/{id}/messages/{msgId}/read`

---

### 5.10 Notifications

**GET** `/customer/notifications`

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "title": "Booking Accepted!",
      "body": "Ali Hassan accepted your booking for May 1st.",
      "type": "booking_update",
      "read_at": null,
      "created_at": "2026-04-17T09:00:00Z"
    }
  ]
}
```

**Mark as Read:**
**PUT** `/customer/notifications/{id}/read`

**UI:** Bell icon in header with unread count badge. Notification dropdown/drawer. Tapping a notification routes to the relevant booking.

---

### 5.11 Customer Profile & Addresses

#### Update Profile
**PUT** `/customer/profile`

All fields are optional. Send only what you want to change.
```json
{
  "name": "Sara Khalil",
  "phone": "03777777",
  "address": "Hamra Street, Beirut",
  "latitude": 33.8938,
  "longitude": 35.5018
}
```

#### Upload Avatar
**PUT** `/auth/profile` *(multipart/form-data)*

| Field | Type |
|-------|------|
| avatar | image (jpg/png max 2MB) |

#### Manage Addresses

**GET** `/customer/addresses`

**POST** `/customer/addresses`
```json
{
  "address": "Hamra Street, Beirut",
  "latitude": 33.8938,
  "longitude": 35.5018,
  "city_id": null,
  "is_default": true,
  "address_type": "home",
  "phone": "03777777",
  "notes": "Ring the bell twice"
}
```
- `address` (required): Full address string.
- `latitude` (required): Numeric latitude.
- `longitude` (required): Numeric longitude.
- `city_id` (optional): City ID integer (from cities table).
- `is_default` (optional): Set as the default address. Only one address can be default.
- `address_type` (optional): e.g. `"home"`, `"work"`, `"other"`. Default: `"home"`.
- `phone` (optional): Contact phone for this address. Must be a valid Lebanese mobile.
- `notes` (optional): Delivery instructions.

**PUT** `/customer/addresses/{id}`

Same fields as POST, all optional (partial update).

**DELETE** `/customer/addresses/{id}`

---

### 5.12 Google Maps Integration (Customer Location)

**Flow:**
1. On first open: request browser location permission (`navigator.geolocation`).
2. Get `{lat, lng}` from browser.
3. Reverse geocode to get human-readable address:
   **GET** `/maps/geocode?lat=33.89&lng=35.50`
4. Pass coordinates to provider search so backend can filter by zone and calculate distance.

**Autocomplete address input:**
**GET** `/maps/places-autocomplete?input=Hamra`

---

---

## 6. Shared Concepts

### 6.1 Booking Status Flow

```
              ┌──────────────────────────────────────────┐
              │              PENDING                      │
              │  (just created, waiting for provider)     │
              └────┬──────────────┬───────────────────────┘
                   │              │
              [Accept]        [Reject]
                   │              │
                   ▼              ▼
             ACCEPTED         REJECTED
                   │
        [Provider proposes reschedule]
                   │
                   ▼
        RESCHEDULE_REQUESTED
          │               │
   [Customer accepts] [Customer rejects]
          │               │
          ▼               ▼
       ACCEPTED         PENDING
          │
   [Provider marks done]
          │
          ▼
       COMPLETED ──► Customer can now leave a review
          
   [Customer cancels at any pending/reschedule stage]
          │
          ▼
       CANCELLED
```

---

### 6.2 Provider Status Flow

```
Subscription recorded by admin
         │
         ▼
is_active = true (unlocked)
         │
Provider toggles: is_active = true/false
         │
At booking.scheduled_at time (auto via cron every minute):
  is_busy = true
         │
Provider marks booking complete:
  is_busy = false
         │
subscription_expires_at passes (auto via cron daily):
  is_active = false (forced inactive)
  Provider sees "Subscription Expired" screen
```

---

### 6.3 Error Response Format

All errors follow this format:
```json
{
  "message": "Human-readable error summary.",
  "errors": {
    "field_name": ["Specific validation message."]
  }
}
```

Common HTTP codes:

| Code | Meaning |
|------|---------|
| `200` | OK |
| `201` | Created |
| `401` | Unauthenticated (no/invalid token) |
| `403` | Forbidden (not allowed for your role) |
| `404` | Resource not found |
| `422` | Validation error |
| `500` | Server error |

---

### 6.4 Pagination

All list endpoints return paginated responses:
```json
{
  "data": [...],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

---

### 6.5 File Uploads

For any endpoint that uploads files (avatar, certification, document, project images):
- Use `Content-Type: multipart/form-data`
- Max sizes: avatars 2MB, certifications 5MB, documents 10MB, project images 5MB each
- Accepted formats: `jpg`, `jpeg`, `png`, `pdf`

---

### 6.6 Swagger / API Explorer

Once the server is running, interactive API docs are available at:

```
http://127.0.0.1:8000/docs
```

Generate docs by running:
```bash
php artisan scribe:generate
```

The Swagger UI lets you test every endpoint with real requests, see all required fields, and view example responses.

---

### 6.7 Recommended Frontend Tech Stack

| Layer | Recommended |
|-------|-------------|
| Framework | React (Next.js) or Vue 3 (Nuxt) |
| State | Redux Toolkit / Pinia |
| HTTP client | Axios with interceptor for Bearer token |
| Maps | Google Maps JavaScript API (Drawing Manager for polygon zones) |
| UI Library | shadcn/ui, Ant Design, or Vuetify |
| Real-time | Laravel Reverb (WebSockets) for chat + notifications |
| Auth storage | `httpOnly` cookie or `localStorage` (choose one) |

---

*Documentation generated for SP Capstone Project — April 2026*
