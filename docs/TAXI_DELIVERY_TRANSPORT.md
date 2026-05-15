# Taxi & delivery transport

Customers can book **taxi** or **parcel delivery** with shared map UI and server-side **Google Routes API** (`computeRoutes`) + pricing.

## Database

- `taxi_bookings` — pickup/destination JSON, passengers, vehicle type, distance, duration, price, `route_data` (polyline).
- `delivery_bookings` — pickup/dropoff, vehicle type (`motorcycle`|`car`|`truck`), package fields, pricing, `route_data`.

## Config

`config/transport.php` — taxi and delivery rate multipliers (override with `.env`: `TRANSPORT_TAXI_*`, `TRANSPORT_DELIVERY_*`, `TRANSPORT_MOTORCYCLE_MAX_KG`). Optional platform commission on estimates: `TRANSPORT_PLATFORM_COMMISSION_PERCENT` (e.g. `15` = 15% platform fee estimate on customer total; adds `platform_fee_estimate` / `partner_payout_estimate` to `/estimate`).

## API (customer, Sanctum + `role:customer`)

Base: `/api/v1`

| Method | Path | Description |
|--------|------|-------------|
| GET | `/customer/transport/route/calculate` | `pickup_lat,lng` + `destination_lat,lng` → distance, duration, `encoded_polyline` |
| GET | `/customer/transport/estimate` | `mode=taxi|delivery` + coords + mode-specific fields → price + polyline |
| POST | `/customer/transport/taxi/book` | Create taxi booking (recalculates route server-side) |
| POST | `/customer/transport/delivery/book` | Create delivery booking; rejects motorcycle if weight &gt; limit |
| GET | `/customer/transport/live-tracking` | `type=taxi|delivery`, `id={uuid}` → booking + fresh ETA (Routes API, same as matrix for one O–D pair) |

**Aliases** (same middleware):

- `GET /route/calculate`
- `GET /booking/estimate-price`
- `GET /booking/live-tracking`
- `POST /taxi/book`
- `POST /delivery/book`

## Example: estimate (taxi)

```
GET /api/v1/customer/transport/estimate?mode=taxi&pickup_lat=33.89&pickup_lng=35.50&destination_lat=33.93&destination_lng=35.58&passenger_count=2&vehicle_type=sedan
```

```json
{
  "data": {
    "mode": "taxi",
    "distance_km": 12.4,
    "duration_minutes": 28.5,
    "encoded_polyline": "...",
    "estimated_price": 24.5,
    "currency": "USD",
    "vehicle_type": "sedan"
  }
}
```

## Frontend (SP-Front)

- **Shared map:** `src/components/maps/PlatformRouteMap.jsx` — polyline (taxi default **red** `#E53935`), pickup **A** / destination **B**, fits bounds, uses `config/maps.js` (`places` + `geometry`).
- **Customer page:** `customer/ride` → `TransportBookingPage.jsx` + `TransportBookingPage.css`.
- **API:** `customerApi.transportEstimate`, `bookTaxi`, `bookDelivery`, etc.

Requires `GOOGLE_MAPS_API_KEY` (backend) and `VITE_GOOGLE_MAPS_API_KEY` (frontend).

**Google Cloud:** enable **Routes API**, **Maps JavaScript API**, **Places API**, and **Geocoding API** (as needed). Legacy **Directions API** is not used; route geometry comes from **Routes API** `computeRoutes`.

If Routes API is not enabled or billing blocks it, the server tries **OSRM** (OpenStreetMap road geometry; encoded polyline works with Maps JS `decodePath`). Configure **`TRANSPORT_OSRM_FALLBACK_ENABLED`** (default `true`) and optional **`TRANSPORT_OSRM_BASE_URL`** for a self-hosted OSRM. Only if OSRM also fails does it fall back to **straight-line Haversine** + ETA from **`TRANSPORT_FALLBACK_SPEED_KMH`** (default `28`). Toggle straight-line-only fallback chain with **`TRANSPORT_ROUTES_FALLBACK_ENABLED`** (default `true`).

## Adopting `PlatformRouteMap` (alias: `GlobalRouteMap`)

Replace inline `GoogleMap` usage in dashboards with:

```jsx
import PlatformRouteMap from '@/components/maps/PlatformRouteMap';

<PlatformRouteMap
  pickup={{ lat, lng }}
  destination={{ lat, lng }}
  encodedPolyline={polyline}
  routeColor="#E53935"
  height="320px"
/>
```

## Delivery validation

- Motorcycle max weight: `config('transport.delivery.motorcycle_max_kg')` (default 15 kg).
- Estimate returns `warning` + `smart_suggested_vehicle` when weight is too high for selected vehicle.
