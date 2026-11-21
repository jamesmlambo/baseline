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
| 2026-11-21 | One `tasks` table for housekeeping, grounds and maintenance, with a unique idempotency key for system-created tasks | Checkout, deep-clean and schedule runs can retry safely (CHK-04); one task list for staff. |
| 2026-11-21 | Room readiness is an explicit `rooms.cleaning_status`, changed only through `TaskManager::setRoomStatus` (audited) | HK-06 needs a clear Ready gate and a trail of who changed it. |
| 2026-11-21 | Turnover runs as a queued job after the checkout commits | Checkout stays fast and never half-creates turnover; the job is idempotent. |
| 2026-11-21 | Rework = a child task with only the failed items, for the same worker; its completion opens a new inspection | HK-05 rework and re-inspection with one mechanism and a full history. |
| 2026-11-21 | Stock is a movement ledger with no editable quantity; rows are immutable | HK-09: counts can always be explained; corrections are visible adjustments. |
| 2026-11-21 | Check-in warns, but does not block, when the room isn't Ready | Blocking would stop reception when the inspector is unavailable; the owner override exists for the Ready gate itself. |
| 2026-11-21 | Raw `attendance_punches` (immutable, composite dedup key) kept apart from derived `attendance_days` | ATT-02: re-imports are harmless, and attendance can always be recalculated or explained from the source events. |
| 2026-11-21 | Roster resolved on the fly (change → leave → holiday → weekly pattern) instead of storing every future day | Fewer rows, and a roster or leave change takes effect everywhere at once. |
| 2026-11-21 | A rostered day is only decided after its clock-out window closes; corrected days are never recalculated | Avoids premature "Absent" and protects audited manual corrections (ATT-03/04). |
| 2026-11-21 | Off-duty suppression lives in `StaffAlert::via()` | EMP-04 applies to every staff notice in one place; urgent notices bypass it. |
| 2026-11-21 | HR access = Owner and Manager only, not Administrator | The administrator role is technical (users, settings); employee and attendance records are personal data (PRIV-01). |
| 2026-11-21 | Hikvision adapter is a stub behind `AttendanceSource`; CSV import is the working path | No live integration until the device test passes (ATT-06); CSV works with the device's own export today. |
| 2026-11-21 | Spa therapists/rooms held in `service_booking_resources` with a GiST exclusion constraint | LSR-03: the database itself refuses double-booking, the same guarantee as room inventory. |
| 2026-11-21 | Service capacity checked under a `FOR UPDATE` lock on the service row | Simple and correct for low booking volumes; no ledger rows per slot needed. |
| 2026-11-21 | Walk-in sales use invoices/receipts with no booking (`invoiceForWalkIn`, `recordWalkIn`) | Day visitors get proper sequential financial documents without a fake room booking. |
| 2026-11-21 | Room charges for services are added on completion, not at booking | Same rule as room service: cancellations and no-shows never need a credit note. |
| 2026-11-21 | Kitchen tickets copy items and allergens at order time | The kitchen sees what was ordered even if the menu changes later. |
| 2026-11-21 | Metrics computed live by `ReportMetrics`; the daily report stores a JSON snapshot | The dashboard is always current, while the 17:00 report stays exactly as sent (REP-03/04). |
| 2026-11-21 | Room revenue spreads a room's accommodation total evenly over its nights | Rates aren't stored per night; even spreading gives a stable, explainable ADR (docs/REPORT_METRICS.md). |
| 2026-11-21 | `reports:daily` runs every minute and is idempotent instead of one 17:00 cron entry | A missed run (deploy, outage) catches up automatically and the report time stays a setting. |
| 2026-11-21 | Web push behind `PushNotifier` with a log driver; log deliveries recorded as "skipped" | No live integration or new package without approval, and the delivery log never claims a push was sent. |
| 2026-11-21 | Campaign attribution = first-touch utm values or referrer host, kept in the session | REP-06 tracking without third-party scripts, cookies or personal data. |
