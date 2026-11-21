# Architecture decisions

Record every compatibility or design decision here (MASTER_PROMPT §0).

| Date | Decision | Why |
|---|---|---|
| 2026-11-20 | Database is `baselineCL` / `baselineCL_testing`, not `baseline_lodge` | `baseline_lodge` belongs to another Laravel project (user instruction). |
| 2026-11-20 | Running on PHP 8.5 (Herd) instead of the 8.4 target | Every installed package resolved on 8.5 without conflicts. |
| 2026-11-20 | Filament 5.11 for `/admin` and `/staff` | Latest stable release that supports Laravel 13. |
| 2026-11-20 | **Filament's built-in multi-factor auth instead of Laravel Fortify** | Only staff sign in, and only through the Filament panels. Filament provides TOTP + recovery codes, login throttling and password reset, so Fortify would duplicate them. Fortify was removed. |
| 2026-11-20 | 2FA required for **every** `/admin` user (including Content editor) | Filament evaluates the "required" flag when routes are registered, before a user exists, so it can't vary by role. This is stricter than SEC-03, never looser. `/staff` keeps 2FA optional. |
| 2026-11-20 | Roles are a PHP enum (`StaffRole`) backed by spatie/laravel-permission roles | Type-safe role checks in code, with DB-backed assignment. Finer permissions will be added per module. |
| 2026-11-20 | Role changes go through `syncRoles()` and permission events (`events_enabled = true`) | So every role change reaches the audit log with who made it. |
| 2026-11-20 | Pest 4 on PHPUnit 12, against PostgreSQL | MASTER_PROMPT requires PostgreSQL for tests so constraints behave as in production. |
| 2026-11-20 | Settings are a key/value table with `is_pending_client_decision` + `decision_reference` | Makes PEND items in FINAL_SPECIFICATION §3.2 visible and auditable in admin. |
| 2026-11-20 | App timezone `Africa/Johannesburg` | Business times are SAST. Timestamp columns use `timestampTz`. |
| 2026-11-21 | Inventory = `room_nights` ledger with a **partial unique index** on active claims, plus a **GiST exclusion constraint** on `booking_rooms` | Two independent database guarantees against double-booking, as INV-03/04 require. Released rows stay as history. |
| 2026-11-21 | Conflicts detected by SQLSTATE `23505`/`23P01`, retried room by room in savepoints | No application-level locks needed. The database decides who wins a race; the loser tries the next free room or gets a clear "no longer available". |
| 2026-11-21 | Stay ranges are `[arrival, departure)`; room blocks use the same exclusive end ("back in service on") | Same-day turnover works naturally (one guest departs, the next arrives). |
| 2026-11-21 | Seasonal rates use inclusive ranges, with no overlap per type (exclusion constraint) | Staff think in "first night / last night". Each night has exactly one price. |
| 2026-11-21 | Booking status changes only through `BookingStateMachine` | One place for transition rules, history and inventory release (BOOK-03). |
| 2026-11-21 | Rate plans deferred to M4 | The spec lists them under INV-01, but there's no client input on which plans exist. Base + seasonal rates cover pricing until then. |
| 2026-11-21 | Local dev runs on port **8001** | Port 8000 is taken by the user's other Laravel project. |
| 2026-11-21 | Public site is plain Blade + Tailwind, no Livewire, JS-free mobile menu (`<details>`) | Fast, accessible and cacheable. Livewire comes in where interactivity is needed (M4 booking flow, M7 guest portal). |
| 2026-11-21 | Media via `spatie/laravel-medialibrary` on the public disk, with queued WebP conversions and responsive srcsets | MASTER_PROMPT names it. Covers WEB-03 responsive images. |
| 2026-11-21 | EXIF stripped by re-encoding the original on `MediaHasBeenAddedEvent` | Conversions alone would leave GPS data in the publicly served original (SEC-08). |
| 2026-11-21 | CMS HTML sanitised at render time with Symfony HtmlSanitizer (already a Filament dependency) | Defence against a compromised editor account; no new package. |
| 2026-11-21 | Sitemap and redirects built in-house, not with `spatie/laravel-sitemap` | A few dozen URLs and one table; a package would add more than it saves. |
| 2026-11-21 | `/book` saves an enquiry, not a booking, until M4 | The online booking flow needs payment rules (M4). An enquiry never claims inventory, so there's no risk of phantom holds. |
| 2026-11-21 | Catch-all `/{slug}` for CMS pages excludes `admin`, `staff`, `stay`, `livewire`, `storage`, `up` | Stops CMS slugs shadowing app routes. Page slugs are also validated against reserved words. |
| 2026-11-21 | Separate tables for quotations, invoices, receipts, credit notes; shared polymorphic `document_lines` | Spec requires separate entities (PAY-01) without duplicating line logic. |
| 2026-11-21 | Document numbers from a locked `document_sequences` row inside the issuing transaction | Gap-free, race-free sequential numbers (PAY-02). |
| 2026-11-21 | Immutability enforced in model events (`IsIssuedDocument`) | Any code path that tries to edit an issued document fails loudly (PAY-03). |
| 2026-11-21 | Payment status derived from the payments ledger (`PaymentLedger::sync`), never typed in | One source of truth; proof of EFT can never become "paid" (PAY-07). |
| 2026-11-21 | Webhooks: event row with unique (gateway, event_id) in its own savepoint | Idempotency without poisoning the outer transaction (PAY-06). |
| 2026-11-21 | Dual refund approval enforced in code **and** a DB CHECK (`approved_by <> requested_by`) | DEC-06 cannot be bypassed even by a bug. |
| 2026-11-21 | Fake payment gateway + `/dev/fake-gateway` page, not registered in production | Exercise the full flow, signatures included, with no real money until a provider is chosen (PEND-07). |
| 2026-11-21 | `Expired → Confirmed` allowed only via `ReinstateBooking`, guarded by "rooms must be re-claimed" | Implements the PEND-02 interim rule without letting a booking hold nothing. |
| 2026-11-21 | Guest email/phone encrypted with HMAC lookup hashes | SEC-07 and BOOK-06 together. |
| 2026-11-21 | PDFs use built-in Helvetica | DejaVu embedding made each PDF ~880 KB; Helvetica gives ~2 KB and covers the characters used. |
| 2026-11-21 | Reception bookings always start as a hold with a staff-chosen expiry | No uncontrolled indefinite holds while hold policy is pending (PEND-01). |
| 2026-11-21 | One `Messenger` with an idempotency key per message, every attempt stored (including skips with a reason) | MSG-02/05: no double messages on retries or repeated scheduler runs; staff can see exactly what a guest received and why something wasn't sent. |
| 2026-11-21 | Spend counted from queued + sent messages' cost; critical templates bypass the cap | MSG-06 hard stop without blocking payment confirmations. |
| 2026-11-21 | Time-based automations in one idempotent command every 5 minutes | Simpler than per-message schedules; repeats are harmless. |
| 2026-11-21 | WhatsApp click-to-chat (`wa.me`) for staff-sent price lists | Works today from the lodge's own WhatsApp with no API or provider contract. |
| 2026-11-21 | Price lists never hold rooms; bookings from them go through the normal flow with source WhatsApp | WA-03: availability re-checked at booking; one booking engine. |
| 2026-11-21 | Booking balance summed over all non-voided invoices (`Booking::balanceDue`) | Extensions and added charges are billed on supplementary invoices, because issued invoices are immutable. |
| 2026-11-21 | Document QR = `/admin/scan?code=<32 random chars>`, revocable per booking | Opaque, no personal data (REC-07); only opens inside an authenticated admin session; every scan audited. |
| 2026-11-21 | Camera scanning with `jsqr` in the browser, plus USB-scanner/typed input | `BarcodeDetector` isn't available in Chrome on Windows; jsQR works everywhere and keeps images on the device. |
| 2026-11-21 | Offline contingency = printable arrivals sheet, not a browser cache | Shared, unmanaged reception devices can't keep cached guest data secure; OFF-03 allows the printed fallback. |
| 2026-11-21 | Room calendar rendered server-side from the room-night ledger (no FullCalendar package) | Shows exactly what the booking engine holds; fewer dependencies. |
| 2026-11-21 | Guest register required fields are a setting (`register.required_fields`) | The legal requirement is unverified (REC-04, COMPLIANCE_TODO). |
| 2026-11-21 | Concurrency tests use `DatabaseTruncation` and a second connection | `RefreshDatabase` transactions are invisible to other connections, so a true race can't be shown with them. |
| 2026-11-21 | Room QR identifies the room only; access needs a 6-digit SMS code to a phone on the stay | A photographed or left-behind QR gives a later guest or visitor nothing (GST-01/02). |
| 2026-11-21 | Portal session bound to stay, room, QR token hash and phone; re-checked on every request | Check-out, room move, QR rotation, phone removal or "End access" end access immediately without tracking sessions (GST-06). |
| 2026-11-21 | Portal codes sent straight to the provider, logged with the code blanked | Staff can see a code was sent but can never read it (GST-03). |
| 2026-11-21 | Room service billed when reception marks it done, inside a row lock | No charges for orders that never arrived; double clicks can't bill twice. |
| 2026-11-21 | Reception alert = Livewire poll (15 s) + Web Audio chime on every admin page | No websockets or push service needed yet (M11 may replace it). |
| 2026-11-21 | Review publish needs consent in code **and** a DB CHECK | A review can never go public without the guest's permission. |
