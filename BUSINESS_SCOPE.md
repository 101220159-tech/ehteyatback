# SP Capstone — Business Scope & Product Requirements
### Complete Frontend Build Prompt

---

## What Is This App?

**SP (Service Platform)** is a **home & professional services marketplace** built for the Lebanese market. It connects customers who need skilled tradespeople (plumbers, electricians, painters, cleaners, etc.) with verified, subscribed service providers. Think of it as a local Uber for home services — the customer opens the app, finds a provider near them, books a time slot, and tracks the job from request to completion.

The platform has **three separate user experiences** (three dashboards, built as one product):

| Dashboard | Who Uses It | Purpose |
|-----------|-------------|---------|
| **Admin Panel** | super_admin, admin | Manage everything — users, providers, zones, services, payments, bookings |
| **Provider App** | Verified provider accounts | Manage profile, availability, bookings, portfolio, earnings, chat |
| **Customer App** | Registered customers | Browse, search, book, track, chat, review |

---

## Core Business Rules — Read These First

These rules drive everything in the UI. Every screen you build must respect them.

### 1. Provider Activation Pipeline

A new provider account goes through this exact pipeline before they can receive bookings:

```
Register (role=provider)
    ↓
Email verified by admin (admin clicks "Verify" or "Approve")
    ↓
Admin records a SUBSCRIPTION PAYMENT for the provider
    ↓
provider.is_active becomes true (provider can now toggle active/inactive)
    ↓
Provider sets up their profile:
  - Bio, experience years
  - Certifications (uploads)
  - Official Documents (uploads)
  - Services they offer (select from catalog + set price)
  - Weekly availability schedule
  - Portfolio / past projects (optional)
    ↓
Provider goes ACTIVE → appears in customer search results
```

**Gate: A provider CANNOT go active if their subscription has expired.**
When `subscription_active = false`, the provider sees a full-screen "Subscription Expired" message and cannot toggle active.

**Gate: An unverified provider cannot log in** (login fails with 403 if email not verified AND role is provider/admin).

---

### 2. Provider Search Sort Order (STRICT)

Customers see providers sorted by exactly this priority:

1. **VIP providers first** (`is_vip = true` floats to the top)
2. **Highest rating** (`rating_avg DESC`)
3. **Most experience** (`experience_years DESC`)

Never change this sort. It is the business model — VIP providers paid extra to be listed first.

---

### 3. Provider Busy System (Automatic)

- A provider becomes **busy** (`is_busy = true`) automatically when a booking they accepted reaches its `scheduled_at` time. This is done by a server cron job every minute.
- The provider becomes **not busy** when they mark the booking as **completed** from their app.
- **Busy providers still appear in search results** — show them with a "Currently Busy" badge. The "Book Now" button is disabled for busy providers.

---

### 4. Chat Permission (Admin-Controlled)

- `allow_chat` on a provider is set **only by the admin**. The provider cannot enable or disable their own chat.
- If `allow_chat = false`, the Chat button is **hidden entirely** on the customer side when viewing that provider.
- If `allow_chat = false` on the provider's own dashboard, show "Chat is disabled. Contact admin to enable."

---

### 5. VIP Distinction

A provider is VIP when an admin records a `vip_upgrade` payment for them. VIP providers:
- Float to the top of search (rule #2 above)
- Show a **gold VIP badge** on their profile card and public profile page

---

### 6. Zone & Area System

The platform serves Lebanon. Geographic coverage is structured as:

- **Zone** = a large region (e.g. "Beirut", "Tripoli", "Mount Lebanon")
- **Area** = a named neighbourhood inside a zone (e.g. "Hamra", "Rawshe", "Verdun")
- Each Area has a **polygon** — an array of GPS coordinates (`{lat, lng}`) drawn by the admin on Google Maps

**How it works for search:**
1. Customer opens app → device requests GPS location
2. Frontend uses the customer's `{lat, lng}` to pass to the search API
3. Backend finds which zone/area the customer is in and filters providers accordingly
4. If GPS denied → show a zone selector dropdown to the customer

**How admin manages it:**
- Admin draws area polygons on a Google Maps embedded view using the **Drawing Manager API**
- When the polygon is complete, the coordinates array is auto-captured and submitted to `POST /admin/zones/{id}/areas`
- Providers are then assigned to one or more zones via multi-select

---

## Platform 1: Admin Dashboard

### Purpose
Full control panel. The admin never does service work — they manage the platform itself.

### Admin Sidebar Navigation
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

### Admin: Dashboard Stats

Single page of KPI cards. API returns:
```json
{
  "users_count": 120,
  "providers_count": 34,
  "services_count": 18,
  "bookings_pending": 12,
  "completed_bookings_count": 340
}
```
Show as 5 KPI metric cards with icons.

---

### Admin: User Management

Full CRUD on all user accounts.

**Create user:**
- Fields: name (required), email (required), password + confirmation (required), role_id — UUID from roles list (required), phone (optional)
- Admin-created accounts are email-verified immediately

**Update user:**
- All fields optional: name, email, password + confirmation, role_id (UUID), phone
- Omit password fields if not changing the password

**Assign role:** Send `{ "role_id": "<uuid>" }` — use the UUID, NOT the role name string.

**Grant direct permissions:** Send `{ "permission_ids": ["<uuid>", "<uuid>"] }` — array of one or more permission UUIDs.

**Edge cases:**
- Deleting a user cascades and removes their provider record, bookings, reviews, etc. Add a confirmation dialog before delete.
- If a user has `google_id` set, they registered via Google — they have no password. Don't show password fields for them.

---

### Admin: Provider Management

The most important admin section. Providers must be managed carefully.

**Provider list columns:**
Name | Verified | VIP | Active | Busy | Chat Enabled | Subscription Expiry | Zones | Actions

**Verify provider:**
```json
POST /admin/providers/{id}/verify
{ "is_verified": true, "verification_notes": "Documents reviewed." }
```
- `is_verified: true` → assigns `provider` role, marks email as verified, sets status to `active`
- `is_verified: false` → reverts to `customer` role, clears email verification (provider locked out)
- Show a confirmation modal before unverifying ("This will lock the provider out of their account")

**Approve provider** (`/approve`): shortcut — same as verify true, no payload needed.

**Toggle Chat:**
```json
PUT /admin/providers/{id}/chat
{ "allow_chat": true }
```
Only the admin can do this. Show as a toggle switch in the provider detail view.

**Toggle VIP:**
```json
PUT /admin/providers/{id}/vip
{ "is_vip": true }
```
This is separate from paying for VIP — recording a `vip_upgrade` payment auto-sets this, but admin can also toggle it manually.

**Assign zones:**
```json
POST /admin/providers/{id}/assign-zones
{ "zone_ids": ["uuid1", "uuid2"] }
```
Multi-select. Existing zone assignments are **kept** (additive, not replacing). To remove, use `DELETE /admin/zones/{zoneId}/providers/{providerId}`.

**Edge cases:**
- A provider with no zones assigned **will not appear in any location-based search**.
- A provider whose subscription expires is **auto-deactivated** by the server daily. The admin must record a new subscription payment to re-activate them.

---

### Admin: Roles & Permissions

- List all roles with their assigned permissions
- Create role: name (required, unique), description (optional), permission_ids (optional array of permission ID integers)
- Update role: same fields. If `permission_ids` is included, it **replaces the entire permission set** for that role.
- Create permission: name (required, unique), description (optional)
- **Never allow deleting the `super_admin` role** — add a guard on the frontend.

**UI pattern:** Role list → click a role → checkbox matrix of all permissions, checkboxes show which are currently assigned → "Save" button calls update.

---

### Admin: Service Categories & Services

**Categories:**
- Simple CRUD: name (required), description (optional)
- No image/icon upload endpoint — icon_url field is nullable and managed manually

**Services:**
- CRUD: name (required), description (optional), category_id UUID (required)
- **There is no `base_price` field on the service creation form.** The price is set by each provider individually when they add the service to their offerings.

**UI pattern:** Two-column layout. Left panel = category list. Right panel = services filtered to the selected category.

---

### Admin: Zones & Areas

**Zone CRUD:**
- name (required, must be unique), description (optional)

**Area CRUD (nested under zone):**
- name (required), coordinates (required array of `{lat, lng}` objects, minimum 3 points)

**UI for drawing areas:**
1. Show a Google Map inside the zone detail page
2. Enable `google.maps.drawing.DrawingManager` with polygon mode
3. When admin finishes drawing, capture the polygon's path as `[{lat, lng}, ...]`
4. Open a dialog asking for the area name
5. Submit to `POST /admin/zones/{zoneId}/areas`

**Viewing zones:**
When loading a zone detail page, draw all its existing area polygons on the map with different colors. Clicking a polygon shows the area name and options to rename or delete.

**Provider assignment:**
Show a multi-select list of all providers below the map. Admin selects providers and clicks "Assign" → calls `POST /admin/zones/{id}/assign-providers` with `provider_ids[]`.
To remove a provider: `DELETE /admin/zones/{id}/providers/{providerId}`.

---

### Admin: Payments & Subscriptions

The admin records cash/transfer payments received from providers. There is no online payment gateway.

**Record a payment:**
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
- `type = "subscription"` → automatically sets `provider.is_active = true` and updates `subscription_expires_at`
- `type = "vip_upgrade"` → automatically sets `provider.is_vip = true`
- `expires_at` must be after `paid_at`

**Filtering:** Filter payments by `provider_id`, `status` (pending/completed/expired), `type` (subscription/vip_upgrade).

**UI:** Show subscription status badge on every provider row throughout admin: green "Active (expires Jul 17)" or red "Expired".

**Edge cases:**
- If admin records a subscription after it expired, the backend sets `is_active = true` again — the provider is re-activated.
- `expires_at` date picker should default to 90 days from today (standard subscription period).

---

### Admin: Bookings (Read-Only)

Admin can only **view** all bookings. No status changes from the admin side — that is the provider's job.

Columns: Customer | Provider | Service | Scheduled At | Status | Created At

Filter by: status, date range, provider.

---

### Admin: Notifications

Send in-app notifications to specific users:
```json
{
  "user_ids": [1, 5, 12],
  "type": "system",
  "title": "Maintenance tonight",
  "content": "Platform down 2-3 AM.",
  "data": {}
}
```
- `user_ids` is an array of user IDs — there is no "send to all" endpoint. Build a multi-select user picker.
- `type` is a free-form string label (e.g. `"system"`, `"promo"`, `"urgent"`).

---

## Platform 2: Provider Dashboard

### What the Provider Can and Cannot Do

| CAN do | CANNOT do |
|--------|-----------|
| Toggle active/inactive | Go active with expired subscription |
| Update their own profile | Change `allow_chat` flag |
| Upload certifications & documents | Change `is_vip` flag |
| Add/remove services from catalog (set own price) | Edit services in the catalog itself |
| Set weekly availability schedule | Accept bookings while inactive |
| Accept/reject/reschedule bookings | Cancel a booking (only customers cancel) |
| Mark bookings as completed | Change booking price after booking |
| Upload portfolio projects | — |
| Chat with customers (if `allow_chat = true`) | — |
| View their earnings | — |

---

### Provider: Status Toggle

**This is the most critical provider feature.**

`GET /provider/status` returns:
```json
{
  "is_active": false,
  "is_busy": false,
  "is_vip": true,
  "subscription_expires_at": "2026-07-17T10:00:00Z",
  "subscription_active": true
}
```

`PUT /provider/status`:
```json
{ "is_active": true }
```

**Rules:**
- If `subscription_active = false` and provider tries to go active → server returns 403. Show: "Your subscription has expired. Contact admin to renew."
- If `is_busy = true`, show "Currently Busy" label — provider cannot clear this manually, it clears when they mark a booking complete.
- Show subscription expiry date prominently. If expiry is within 7 days, show a yellow warning.

---

### Provider: Profile Update

`PUT /provider/profile`

Updates **both** the user record and the provider record in one request:
```json
{
  "name": "Ali Hassan",
  "email": "ali@example.com",
  "phone": "70123456",
  "latitude": 33.8938,
  "longitude": 35.5018,
  "bio": "5 years experience in residential plumbing.",
  "experience_years": 5
}
```
- Phone must be a **Lebanese mobile number** (e.g. `70XXXXXX`, `03XXXXXX`, `71XXXXXX`). Show a format hint.
- `latitude` / `longitude` come from the location picker — use Google Maps or device GPS.

**Separate location endpoint** (call this when the provider taps "Update Location"):
`PUT /provider/profile/location`
```json
{ "latitude": 33.8938, "longitude": 35.5018, "address": "Hamra, Beirut" }
```

---

### Provider: Certifications

Upload official professional certifications (electrician license, plumbing certificate, etc.).

`POST /provider/certifications` — multipart/form-data:
```
title: "Master Electrician License"   (required)
issuer: "Lebanese Ministry of Labor"  (optional)
issued_at: "2020-06-01"               (optional, YYYY-MM-DD)
expires_at: "2026-06-01"              (optional, YYYY-MM-DD)
file: <PDF or image, max 5MB>         (optional)
```

`PUT /provider/certifications/{id}` — same fields, all optional (partial update).

**UI:** Card list. Each card shows title, issuer, expiry (with red badge if expired). "Download" link opens `file_url`. "+ Add" button opens modal.

**Edge cases:**
- Certification with no `file_url` still shows in the list — provider may have just entered text info.
- Highlight expired certifications (`expires_at < today`) with a red badge. This is visible to customers too.

---

### Provider: Official Documents

Upload identity/business documents for admin verification.

`POST /provider/documents` — multipart/form-data:
```
type: "id_card"         (required — one of: id_card, trade_license, insurance, certificate, other)
title: "National ID"    (required)
file: <PDF or image, max 10MB>  (required)
```

**No update endpoint** — to change a document, delete it and re-upload.

**Types mapping for display:**
| Type | Display Label |
|------|---------------|
| `id_card` | National ID |
| `trade_license` | Trade License |
| `insurance` | Insurance Certificate |
| `certificate` | Professional Certificate |
| `other` | Other Document |

---

### Provider: Services I Offer

Provider selects services from the platform catalog and sets their own price.

`POST /provider/services`:
```json
{ "service_id": "uuid", "price": 65.00 }
```
If the provider already has that service, it updates the price (idempotent).

`PUT /provider/services/{id}`:
```json
{ "price": 70.00 }
```

`DELETE /provider/services/{id}`: Remove service offering.

**UI:** Two-panel layout:
- Left: all platform services grouped by category (fetched from `GET /services`)
- Right: provider's current offerings with price display and edit option
- Clicking a service on the left opens a price input dialog → submits to POST

---

### Provider: Availability Schedule

Weekly recurring schedule. Each slot = one day + start time + end time.

`POST /provider/availability`:
```json
{
  "day_of_week": "monday",
  "start_time": "09:00",
  "end_time": "17:00",
  "is_available": true
}
```

> **`day_of_week` is a string.** Use consistent naming — either always full names (`"monday"`, `"tuesday"`) or always numbers as strings (`"1"`, `"2"`). The backend stores exactly what you send.

`PUT /provider/availability/{id}` — all 4 fields required (full replacement, not partial).

**There is no DELETE endpoint for availability.** To "disable" a slot, send `PUT` with `is_available: false`.

**UI:** Weekly grid (7 columns). Each column = one day. Time slots shown as colored bars within the day. Click day to add or edit slots via a time-range picker.

**Edge cases:**
- A provider with NO availability slots set will never match the booking availability check → customers will always see "Provider not available at requested time."
- Multiple slots per day are allowed (e.g. 9 AM–12 PM and 2 PM–6 PM on Monday).

---

### Provider: Portfolio / Projects

`POST /provider/projects`:
```json
{
  "title": "Kitchen Renovation Plumbing",
  "description": "Full kitchen plumbing overhaul for 3BR apartment."
}
```
> You can also send `"name"` instead of `"title"` — the backend accepts both.

**Upload images to a project** (separate endpoint):
`POST /provider/projects/{id}/images` — multipart/form-data:
```
images[]: <image file, max 5MB>
images[]: <image file, max 5MB>
```
Multiple images in one request. Max dimensions 8000×8000px.

You can also pass `image` (single file) or `image_url` (URL string) on the create/update endpoint directly.

**UI:** Project cards with image gallery. Each card shows project title, description, and image thumbnails (click to enlarge). "+ New Project" button, "Upload Photos" button on each card.

---

### Provider: Booking Management

The booking lifecycle from the provider's perspective:

```
PENDING → Provider sees "New Booking" notification
        → [Accept]     → status: accepted  → customer notified
        → [Reject]     → status: rejected  → customer notified (with optional reason)
        → [Reschedule] → status: reschedule_requested → customer must respond

ACCEPTED → At scheduled_at time → is_busy = true (auto)
         → [Mark Complete] → status: completed → customer notified → review prompt
```

**Accept:** `PUT /provider/bookings/{id}/accept` — no payload.

**Reject:** `PUT /provider/bookings/{id}/reject`
```json
{ "reason": "I am not available that day." }
```
Reason is optional but show a textarea for it.

**Propose reschedule:** `POST /provider/bookings/{id}/reschedule`
```json
{
  "proposed_at": "2026-05-02T14:00:00",
  "message": "Can we do it the next day at 2 PM?"
}
```
- `proposed_at` must be in the future
- Status becomes `reschedule_requested` — provider waits for customer response

**Mark complete:** `PUT /provider/bookings/{id}/complete` — no payload.
- Only works on `accepted` bookings.
- After completing, `is_busy = false` automatically.

**Booking list tabs:** Pending | Accepted | Reschedule Requested | Completed | Rejected/Cancelled

**Reschedule UI state:** When status is `reschedule_requested` and the provider proposed the reschedule, show "Awaiting customer response" badge. The provider cannot do anything else until the customer responds.

---

### Provider: Chat

Only available if `provider.allow_chat = true`.

If disabled: show "Chat feature is disabled. Contact the admin to enable chat on your account."

When enabled:
- Chat list shows all customer conversations ordered by `updated_at`
- Messages list shows `sender_id` — compare with the current user's ID to know left/right bubble alignment
- Typing indicator uses WebSocket (Laravel Reverb) — broadcast `typing` event
- `POST /provider/chats/{id}/messages`:
```json
{ "message_text": "I can come tomorrow at 10 AM.", "message_type": "text" }
```

**`message_type`:** `"text"` or `"image"` (image upload not yet implemented — use `"text"` for now).

---

### Provider: Earnings

`GET /provider/earnings` — read-only summary:
```json
{
  "total_earnings": 4500.00,
  "this_month": 1200.00,
  "completed_bookings": 45
}
```

UI: KPI cards + optional chart (bar chart by month).

---

## Platform 3: Customer App

### Customer Flow (Main Journey)

```
Open App
    ↓
Browse categories / Search providers
    ↓
View provider profile
    ↓
Select service + date/time → Create booking
    ↓
Wait for provider to accept/reject
    ↓
If accepted → wait for scheduled time
    ↓
If provider proposes reschedule → Accept or Reject
    ↓
Service happens → Provider marks complete
    ↓
Customer leaves review
```

---

### Customer: Browse & Search

**Browse categories (public — no auth needed):**
`GET /categories` — icon grid on home screen

**Services in a category (public):**
`GET /categories/{id}/services`

**Search providers:**
`GET /customer/providers/search`

Query params:

| Param | Type | Notes |
|-------|------|-------|
| `service_id` | UUID | Filter to providers offering this service |
| `category_id` | UUID | Filter to providers offering any service in this category |
| `keyword` or `q` | string | Text search in bio, name, services |
| `min_rating` | number | e.g. `4` for 4+ stars |
| `latitude` | decimal | Customer GPS latitude |
| `longitude` | decimal | Customer GPS longitude |
| `radius_km` | number | Max distance from customer |

**Search suggestions (autocomplete):**
`GET /customer/providers/search/suggestions?q=plumber`
Returns `{ categories: [], services: [], providers: [] }` — use for a live search dropdown.

**Provider card (in results):**
- VIP gold badge if `is_vip = true`
- Rating stars
- "Currently Busy" badge if `is_busy = true` (still visible, Book Now disabled)
- Distance label (if coordinates provided)
- Services list preview
- Zones served

---

### Customer: View Provider Public Profile

`GET /providers/{id}` (public — no auth needed)

Shows: avatar, name, bio, rating, total reviews, experience years, VIP badge, services with prices, zones, portfolio projects.

**Action buttons:**
- "Book Now" — disabled and grayed if `is_busy = true` or `is_active = false`
- "Chat" — only shown if `allow_chat = true`

---

### Customer: Create a Booking

`POST /customer/bookings`
```json
{
  "provider_id": "uuid",
  "service_id": "uuid",
  "scheduled_at": "2026-05-01T10:00:00",
  "duration_minutes": 60,
  "customer_notes": "Leak under kitchen sink",
  "customer_latitude": 33.8938,
  "customer_longitude": 35.5018,
  "customer_address": "Hamra, Beirut"
}
```

**Validation errors to display:**

| Error message | What to show |
|---------------|--------------|
| "Provider is not currently active." | "This provider is currently inactive." |
| "Provider is not available at the requested time." | Highlight the selected time slot as unavailable |
| "This time slot is already taken." | "This time is already booked. Please choose another." |

**Date/time picker UX:**
1. Show a calendar. Highlight only days that match the provider's `day_of_week` availability slots.
2. When a day is selected, show time slots within the provider's available hours for that day.
3. Cross out slots that overlap with the provider's existing accepted bookings (fetch from provider's public profile or availability endpoint).
4. `duration_minutes` defaults to 60. Could be a dropdown: 30, 60, 90, 120, 180 minutes.

**Address picker:**
- Prefill from device GPS
- Allow customer to pick from their saved addresses
- Or enter manually + map pin

---

### Customer: My Bookings

**List:** `GET /customer/bookings?status=pending`
Filter tabs: All | Pending | Accepted | Completed | Cancelled

**Key UI states:**

| Booking Status | What customer sees | Actions |
|---------------|-------------------|---------|
| `pending` | "Awaiting provider confirmation" | [Cancel] |
| `accepted` | "Confirmed — May 1st at 10 AM" | (no actions) |
| `reschedule_requested` | "Provider proposed: May 2nd 2 PM" | [Accept New Time] [Reject] |
| `completed` | "Completed" | [Leave Review] (if no review yet) |
| `rejected` | "Provider declined" | [Book Again] |
| `cancelled` | "Cancelled" | — |

---

### Customer: Reschedule Workflow

When provider proposes a new time, a banner appears:

> "Ali Hassan proposed a new time: **May 2nd at 2:00 PM**
> "Can we do it the next day at 2 PM?"
> [Accept New Time]   [Reject]"

`PUT /customer/bookings/{id}/reschedule/accept` — no payload → booking becomes `accepted` with new time
`PUT /customer/bookings/{id}/reschedule/reject` — no payload → booking returns to `pending`

**Edge case:** If `latest_reschedule_request` is null or its status is not `"pending"`, don't show the accept/reject buttons.

---

### Customer: Cancel a Booking

`PUT /customer/bookings/{id}/cancel` — no payload.

**Only works when status is `pending` or `reschedule_requested`.**
If the booking is `accepted` or later, show "Booking cannot be cancelled at this stage. Contact the provider."

---

### Customer: Leave a Review

`POST /customer/reviews`
```json
{
  "provider_id": "uuid",
  "rating": 5,
  "comment": "Ali was professional and quick!"
}
```
- `provider_id` is required — it's the provider UUID (not the booking ID).
- `rating`: integer 1–5 (required).
- `comment`: optional text, max 5000 chars.
- Show this review form only after a booking is `completed`.

`PUT /customer/reviews/{id}` — update an existing review:
```json
{ "rating": 4, "comment": "Good but slightly late." }
```
Both fields optional on update.

**Edge case:** A customer who hasn't had a `completed` booking with a provider should not see the review button. Guard this on the frontend.

---

### Customer: Chat

`POST /customer/chats` — open or retrieve a chat with a provider:
```json
{ "provider_id": "uuid" }
```
Returns existing chat if one already exists (idempotent).

**Only show the "Chat" button if `provider.allow_chat = true`.** Opening a chat when `allow_chat = false` will still work at the API level, but the UI should never offer the option.

Messages: same structure as provider chat. `sender_id` vs current user ID determines bubble side.

Send message: `POST /customer/chats/{id}/messages`
```json
{ "message_text": "Hi, when can you come?", "message_type": "text" }
```

---

### Customer: Notifications

`GET /customer/notifications` — paginated notification list.

Each notification has `read_at: null` if unread. Show unread count badge on bell icon.

`PUT /customer/notifications/{id}/read` — mark one as read. (No "mark all read" endpoint — handle per-notification.)

**Notification types and routing:**

| `type` | Where to route on tap |
|--------|----------------------|
| `booking_update` | booking detail page |
| `payment` | payments/subscription page |
| `system` | notification drawer only |

---

### Customer: Profile

`PUT /customer/profile` — update account info:
```json
{
  "name": "Sara Khalil",
  "phone": "03777777",
  "address": "Hamra Street, Beirut",
  "latitude": 33.8938,
  "longitude": 35.5018
}
```
All fields optional (send only what changed).

`PUT /auth/profile` — update avatar (multipart/form-data):
```
avatar: <image file, max 2MB, jpg/png>
```

**Update location only:** `PUT /customer/profile/location`
```json
{ "latitude": 33.8938, "longitude": 35.5018, "address": "Hamra, Beirut" }
```

---

### Customer: Saved Addresses

Multiple saved addresses per customer. One can be marked as default.

`POST /customer/addresses`:
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
- `address` + `latitude` + `longitude` are required.
- `address_type`: `"home"`, `"work"`, or `"other"`.
- Setting `is_default: true` un-defaults all other addresses automatically.

`PUT /customer/addresses/{id}` — partial update, all fields optional.

---

### Customer: Google Maps Integration

1. On first app open, call `navigator.geolocation.getCurrentPosition()`
2. Use the coordinates to reverse geocode: `GET /maps/geocode?lat=33.89&lng=35.50`
3. Show the resolved address in a location bar at the top of the search screen
4. Pass `latitude` + `longitude` to all provider searches

**Address autocomplete (search bar):**
`GET /maps/places-autocomplete?input=Hamra`
Use this to power the address input field when the customer types a location.

---

## Authentication Flows

### Email Registration

**Customer:**
```json
POST /auth/register
{
  "name": "Sara",
  "email": "sara@example.com",
  "password": "Secret@123",
  "password_confirmation": "Secret@123",
  "role": "customer",
  "phone": "03777777"
}
```

**Provider:**
```json
POST /auth/register
{
  "name": "Ali",
  "email": "ali@example.com",
  "password": "Secret@123",
  "password_confirmation": "Secret@123",
  "role": "provider",
  "provider_phone": "70123456"
}
```
`provider_phone` is **required** for provider registration. It must be a valid Lebanese mobile number.

After registration: show "Check your email for a verification link" screen.

---

### Login

```json
POST /auth/login
{
  "email": "ali@example.com",
  "password": "Secret@123"
}
```

**Handle these login errors:**
- `401` → wrong email/password → show "Invalid credentials"
- `403` with message "Email address is not verified." → show "Check your email for the verification link. [Resend]"

**On success**, read `user.role_name` to decide which dashboard to route to:
```
role_name = "super_admin" or "admin"  → /admin/dashboard
role_name = "provider"                → /provider/dashboard
role_name = "customer"                → /customer/home
```

---

### Google OAuth (SPA flow)

1. Show "Sign in with Google" button
2. Redirect to `GET /auth/google` — this takes the user to Google's login page
3. After Google authentication, browser is redirected to `GET /auth/google/callback`
4. Backend returns `{ token, user }` — same structure as email login
5. Google auth always creates a `customer` role account. Providers must register by email.

---

### Change Password

`POST /auth/change-password` (authenticated):
```json
{
  "current_password": "OldPass@123",
  "password": "NewPass@123",
  "password_confirmation": "NewPass@123"
}
```
**Note: users who signed up via Google have no password** — don't show the change password form if `user.google_id` is set and `password` was never set.

---

### Forgot / Reset Password

`POST /auth/forgot-password` → `{ "email": "sara@example.com" }` → sends reset link by email

`POST /auth/reset-password`:
```json
{
  "token": "<from email link>",
  "email": "sara@example.com",
  "password": "NewPass@123",
  "password_confirmation": "NewPass@123"
}
```

---

## Edge Cases Summary

These are situations that will break the UI if not handled:

| Situation | What to do |
|-----------|------------|
| Provider has no zones | Show warning in provider dashboard: "You have no zones assigned. Contact admin." Not visible in customer search. |
| Provider `subscription_active = false` | Show full-screen "Subscription Expired" blocker. Disable status toggle. |
| Provider `is_busy = true` | Show "Currently Busy" badge. Disable "Book Now". |
| Customer tries to cancel `accepted` booking | Show "Cannot cancel after acceptance. Contact provider." |
| `allow_chat = false` | Hide chat button entirely from customer view. Show disabled message in provider dashboard. |
| Booking `reschedule_requested` but `latest_reschedule_request = null` | Server inconsistency — show generic "Reschedule Pending" without accept/reject buttons |
| Provider with no availability set | Customer gets "Provider not available" on every time they try to book |
| User logs in with Google but tries to change password | Hide password change form or show "Linked with Google — no password needed" |
| Admin deletes a user who has accepted bookings | DB cascades — those bookings become orphaned. Consider blocking the delete if active bookings exist (add a frontend check). |
| Token expired (401 on any request) | Redirect to login screen, clear stored token |

---

## Real-time Features (WebSockets via Laravel Reverb)

The backend uses Laravel Reverb for WebSocket broadcasting.

| Feature | Channel | Event |
|---------|---------|-------|
| New chat message | `chat.{chatId}` | `MessageSent` |
| Message read receipt | `chat.{chatId}` | `ChatMessageRead` |
| Typing indicator | `chat.{chatId}` | `ChatTyping` |
| Booking status change | `user.{userId}` | via notification |

Use Laravel Echo with the Reverb driver on the frontend:
```js
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher
const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  wssPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
})
```

---

## Technology Recommendations

| Layer | Recommended choice |
|-------|--------------------|
| Framework | React 18 (Next.js 14) or Vue 3 (Nuxt 3) |
| State management | Zustand / Redux Toolkit (React) or Pinia (Vue) |
| HTTP client | Axios + interceptor that attaches Bearer token and handles 401 redirect |
| Maps | Google Maps JavaScript API v3 (Drawing Manager for admin zones, Places for autocomplete) |
| Real-time | Laravel Echo + Reverb (pusher-compatible WebSocket) |
| UI library | Tailwind CSS + shadcn/ui (React) or Tailwind + HeadlessUI |
| Auth storage | `localStorage` for the token (or `httpOnly` cookie via Next.js proxy) |
| File uploads | Use `FormData` with `Content-Type: multipart/form-data` |
| Date/time | `dayjs` or `date-fns` for formatting `scheduled_at` and `subscription_expires_at` |
| Map geocoding | Google Maps Geocoding API (for reverse geocode on customer location) |

---

## API Base URL & Token

```
Base URL: http://127.0.0.1:8000/api/v1
Auth header: Authorization: Bearer {token}
Content-Type: application/json  (except file uploads — use multipart/form-data)
```

On app load, always call `GET /auth/me` to restore the session and get the current user's role and permissions. If it returns 401, token is expired — redirect to login.

Interactive API explorer (Swagger): `http://127.0.0.1:8000/docs`
