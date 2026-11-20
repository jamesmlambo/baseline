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
| 2026-11-21 | Concurrency tests use `DatabaseTruncation` and a second connection | `RefreshDatabase` transactions are invisible to other connections, so a true race can't be shown with them. |
