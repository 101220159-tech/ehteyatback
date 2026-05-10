# RECO / NexVex Chatbot — Advanced Manual Test Checklist (91 cases)

Use this list for exploratory QA, staging sign-off, and release gates. Mark each row **Pass** or **Fail** (and link bugs).

---

## A. Phase 1 — Intent recognition (24)

- [ ] **A1** Plumbing keyword resolves to `plumbing` primary service.
- [ ] **A2** Electrical keyword resolves to `electrical`.
- [ ] **A3** Cleaning keyword resolves to `cleaning`.
- [ ] **A4** Painting keyword resolves to `painting`.
- [ ] **A5** AC / HVAC phrasing resolves to `ac_repair` (word boundary for short `ac`).
- [ ] **A6** Carpentry keyword resolves to `carpentry`.
- [ ] **A7** Gardening / landscaping resolves to `gardening`.
- [ ] **A8** Moving / movers phrasing resolves to `moving`.
- [ ] **A9** `emergency` sets urgent flag.
- [ ] **A10** `urgent` sets urgent flag.
- [ ] **A11** `asap` sets urgent flag.
- [ ] **A12** Budget vocabulary maps to `budget` price preference.
- [ ] **A13** Premium / “top rated” vocabulary maps to `premium`.
- [ ] **A14** Beirut area name extracted as location.
- [ ] **A15** Hamra extracted.
- [ ] **A16** Ashrafieh extracted.
- [ ] **A17** Verdun extracted.
- [ ] **A18** Gemmayze extracted.
- [ ] **A19** Mar Mikhael extracted.
- [ ] **A20** Tripoli extracted.
- [ ] **A21** Jounieh extracted.
- [ ] **A22** Byblos extracted.
- [ ] **A23** Batroun extracted.
- [ ] **A24** Zahle extracted.

---

## B. Chatbot HTTP API — `/api/v1/customer/chatbot/*` (20)

- [ ] **B1** Unauthenticated `POST /message` returns 401.
- [ ] **B2** Non-customer token (e.g. provider) returns 403 on `POST /message`.
- [ ] **B3** Valid body returns `success: true` and `data.message`.
- [ ] **B4** Response includes `data.intent` object.
- [ ] **B5** Response includes `data.recommendations` (array).
- [ ] **B6** Response includes `data.google_places` (array).
- [ ] **B7** First message without `conversation_id` creates a new `conversation_id`.
- [ ] **B8** Second message with same `conversation_id` reuses thread.
- [ ] **B9** `GET /conversations` lists threads for the authenticated customer.
- [ ] **B10** `GET /conversations/{id}` returns 404 for another user’s conversation.
- [ ] **B11** Empty `message` rejected (422).
- [ ] **B12** One-character `message` rejected (422).
- [ ] **B13** `message` longer than 1000 chars rejected (422).
- [ ] **B14** Exactly 1000-character `message` accepted (200).
- [ ] **B15** Invalid `conversation_id` format rejected (422).
- [ ] **B16** Unknown but valid UUID for `conversation_id` rejected (422).
- [ ] **B17** Optional `latitude` / `longitude` accepted when numeric.
- [ ] **B18** Non-numeric `latitude` rejected (422).
- [ ] **B19** Pagination on conversations behaves (page 2 loads).
- [ ] **B20** Rate limit / throttle does not block normal single-user usage (smoke).

---

## C. Conversation quality & providers (12)

- [ ] **C1** Follow-up “in Hamra” inherits prior service from thread.
- [ ] **C2** Bot reply references NexVex providers when recommendations exist.
- [ ] **C3** Fallback text when no NexVex matches is polite and actionable.
- [ ] **C4** Premium intent prefers higher-rated providers (≥ 4.0).
- [ ] **C5** With GPS, recommendations include `distance_km` when coords known.
- [ ] **C6** Recommendation card includes name, rating, and location.
- [ ] **C7** Tapping a recommendation opens provider profile in app.
- [ ] **C8** Google Places cards (if enabled) are labeled as non-NexVex where appropriate.
- [ ] **C9** Long conversation history does not break intent merge.
- [ ] **C10** Mixed Arabic / English input still yields sensible intent (if supported).
- [ ] **C11** Off-topic message returns general intent without crash.
- [ ] **C12** Conversation can be abandoned and resumed with new thread.

---

## D. Bookings after chat (15)

- [ ] **D1** Customer can create booking from provider profile discovered via chat.
- [ ] **D2** Booking `scheduled_at` in the future is accepted.
- [ ] **D3** Booking rejected when provider inactive.
- [ ] **D4** Booking rejected when provider not verified (if business rule applies).
- [ ] **D5** Conflict detection blocks overlapping accepted slots.
- [ ] **D6** Provider with no availability rows skips strict slot check.
- [ ] **D7** Provider with schedule rejects out-of-window times.
- [ ] **D8** Price pulled from `provider_services` pivot when present.
- [ ] **D9** Customer sees new booking in `GET /customer/bookings`.
- [ ] **D10** Provider receives in-app notification for new booking.
- [ ] **D11** Customer can cancel pending booking (if allowed).
- [ ] **D12** Reschedule request flow still works after chat-sourced booking.
- [ ] **D13** Notes from booking appear on provider view.
- [ ] **D14** Invalid `service_id` for provider rejected (422).
- [ ] **D15** Invalid `provider_id` rejected (422).

---

## E. Security & abuse (10)

- [ ] **E1** “Ignore previous instructions” does not change API auth behaviour.
- [ ] **E2** SQL-like payloads do not alter schema / data (smoke + DB intact).
- [ ] **E3** XSS-like payloads in chat are not reflected as executable HTML in API JSON.
- [ ] **E4** System prompt / “You are a…” extraction attempts return no hidden system text fields.
- [ ] **E5** Horizontal privilege: cannot read other users’ chatbot conversations.
- [ ] **E6** Token without `customer` role cannot hit customer chatbot routes.
- [ ] **E7** Throttle triggers on excessive identical requests (abuse).
- [ ] **E8** Large payload rejected at validation layer before LLM.
- [ ] **E9** PII in messages is not logged verbatim in production logs (policy check).
- [ ] **E10** HTTPS only in production for chatbot endpoints.

---

## F. Performance & reliability (10)

- [ ] **F1** Cold first message under 10s P95 on staging (real Ollama).
- [ ] **F2** Warm follow-up under 3s P95 on staging.
- [ ] **F3** Ten simulated concurrent customers: no 5xx (use k6/Locust).
- [ ] **F4** Ollama down: user still gets fallback JSON (no uncaught exception).
- [ ] **F5** Google Places disabled: chat still succeeds.
- [ ] **F6** DB connection loss surfaces controlled 5xx/health, not hang.
- [ ] **F7** Memory usage stable over 50-turn conversation (leak check).
- [ ] **F8** Pagination on conversations remains fast with 100+ threads.
- [ ] **F9** Chatbot tables migrated cleanly on fresh deploy.
- [ ] **F10** Backup / restore includes `chatbot_messages` and `chatbot_conversations`.

---

**Total:** 24 + 20 + 12 + 15 + 10 + 10 = **91** checklist items.
