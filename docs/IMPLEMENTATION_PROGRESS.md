# Implementation progress

New session? Read `docs/MASTER_PROMPT.md`, `docs/FINAL_SPECIFICATION.md` and this file, then continue from **Next**.

## Current status

- **Milestone 1 — Foundation:** done.
- **Milestone 2 — Rooms & booking engine:** done (engine and admin screens). There is no booking UI yet: bookings are created through `PlaceBooking`, and the screens come in M4/M5.
- **Milestone 3 — Public website:** done (all 20 pages, CMS, SEO, redirects, enquiries).
- **Milestone 4 — Reservations & finance:** done (online booking + fake payment provider, guest profiles, extras, quotations/invoices/receipts/credit notes, EFT verification, dual-approval refunds, admin screens).
- **Milestone 5 — Reception:** done (check-in/out, guest register, extend stay, charges, front-desk log, document QR + scanner, room calendar, dashboard, offline arrivals sheet).
- **Next:** **Milestone 6 — Messaging & WhatsApp bookings** (SMS/WhatsApp/email interfaces with log drivers, templates, consent, spend cap, live WhatsApp price lists; FINAL_SPECIFICATION §10, §14).

## Environment

| Item | Value |
|---|---|
| Laravel | 13.43 |
| PHP | 8.5.12 (Herd) |
| PostgreSQL | 18.4, user `postgres`, local trust auth (no password); `btree_gist` extension used |
| App database | `baselineCL` |
| Test database | `baselineCL_testing` (used by `phpunit.xml`) |
| Not ours | `baseline_lodge`, `baseline_lodge_test`, `baseline_lodge_testing` belong to another Laravel project. **Do not touch them.** |
| Local URL | **http://127.0.0.1:8001** (`php artisan serve --port=8001`, or the VS Code task **Dev: all**). Port 8000 is used by the user's other Laravel project. |
| Git | `main`, author James. No remote. |

## Milestone 1 — Foundation (20 Nov 2026)

Done: PostgreSQL, Laravel Boost, Filament 5 panels `/admin` and `/staff` with TOTP 2FA (required on `/admin`), 8 staff roles, staff accounts screen, system settings with PENDING flags, audit log, local demo staff accounts, public layout and placeholder homepage, VS Code workspace, project rules (`.ai/guidelines`, merged into `CLAUDE.md`/`AGENTS.md`), README and docs.

## Milestone 2 — Rooms & booking engine (21 Nov 2026)

### Database
- `accommodation_types`, `rooms`, `seasonal_rates`. Seasons cannot overlap per type (GiST exclusion constraint).
- `bookings`, `booking_rooms`, `room_blocks`, `room_nights`, `booking_status_histories`.
- **Double-booking protection in PostgreSQL:**
  - `room_nights_one_active_claim`: unique (room, night) where not released (INV-03).
  - `booking_rooms_no_overlap`: exclusion constraint on overlapping active stays per room (INV-04).
  - CHECKs: departure after arrival, a hold must have an expiry, no negative money, valid occupancy.

### Code
- `App\Support\Money` + `MoneyCast` (integer cents, `R 1 234.50` formatting). `App\Support\StayDates`.
- Enums: `BookingStatus` (with allowed transitions), `PaymentStatus`, `BookingSource`, `RoomBlockReason`.
- `Services\Pricing\RateCalculator`: seasonal rate per night, else base rate, plus extra guests.
- `Services\Availability\AvailabilityService`: the single availability source (search, available rooms, room check).
- `Services\Inventory\RoomNightLedger`: claims and releases room nights; detects conflicts by SQLSTATE.
- `Actions\Bookings\PlaceBooking`: creates the booking and assigns a physical room per requested room in one transaction. Tries the next room if a concurrent request wins. Holds always get an expiry (setting `booking.hold_minutes_online`, PEND-01).
- `Services\Bookings\BookingStateMachine`: the only way to change status. Rejects invalid moves, writes history, and releases inventory on Cancelled, No-show and Expired.
- `Actions\Bookings\ReleaseExpiredHolds` + scheduled `bookings:release-expired-holds` every minute.
- `Actions\Bookings\ReassignRoom`: reason required, audited, old assignment kept as history.
- `Actions\Inventory\BlockRoom` / `ReleaseRoomBlock`: refuses to block booked nights.

### Admin (`/admin › Bookings`)
- **Rooms & rates:** room types with Rooms and Seasonal rates tabs. Rand inputs are stored as cents. Types and rooms are deactivated, never deleted.
- **Room blocks:** take a room out of service; release button.
- Access: Administrator, Owner and Manager manage; Receptionist views only.

### Demo data
`DemoAccommodationSeeder` (local only): "Demo Standard Room" ×4 and "Demo Family Room" ×2, rooms "Demo Room 01–06", flagged `is_demo`. Prices are placeholders.

### Tests
81 passing, including a **two-connection race test** (`tests/Concurrency`) proving a rival claim waits on, then is refused by, an uncommitted booking of the last room.

### Deferred (by design)
- Rate plans (e.g. B&B vs room-only) and enforcing a season's minimum nights: M4 booking flow.
- See M3 for what was added since.

## Milestone 3 — Public website (21 Nov 2026)

### Public pages (all 20 from FINAL_SPECIFICATION §5.1)
`/` home · `/about-us` · `/rooms` · `/rooms/{slug}` · `/features-and-amenities` · `/services` · `/swimming-pool-and-day-passes` · `/braai-facilities` · `/vip-areas` · `/spa-and-massage` · `/news` + `/news/{slug}` · `/faqs` · `/contact-us` · `/check-availability` · `/book` · `/guest-portal` · `/house-rules` · `/booking-and-cancellation-policies` · `/privacy-policy` · `/official-communication-policy`. Plus `/sitemap.xml` and a branded 404 page.

- **Homepage:** hero, availability search, featured rooms with live "from" prices, facilities, latest 3 news posts, call to action. Guest reviews arrive with M7. The tagline and intro come from settings (PENDING, empty).
- **Availability search:** live counts and prices from `AvailabilityService`. "Request this room" goes to `/book`.
- **`/book` is a booking *request* for now:** it shows the nightly price breakdown, requires accepting the policies, keeps marketing consent separate and optional, and saves an enquiry. It **holds no room** and tells the guest so. M4 replaces it with real online booking and payment.
- **Contact form** with honeypot, rate limit (5 a minute per IP), email-or-phone rule, and a hashed IP. Notifies `website.enquiry_email` if set; the email contains no personal data.
- **SEO:** meta description, canonical, Open Graph, `noindex` outside production, sitemap, JSON-LD (LodgingBusiness, HotelRoom, Event, FAQPage).
- **Grading stars** are shown only when `website.grading_verified` = yes (WEB-07).
- **Accessibility basics:** skip link, landmarks, labelled form fields with error descriptions, `aria-current`, focus outlines, a mobile menu that works without JavaScript (`<details>`). Checked at 375 px with no horizontal scroll. A full WCAG/Lighthouse audit is scheduled for M13.
- **Security:** CMS HTML is sanitised (`App\Support\SafeHtml`, Symfony HtmlSanitizer). Photo metadata (EXIF/GPS) is stripped on upload (`StripImageMetadata` listener).
- Contact details are read from settings only; empty until the client supplies them.

### Admin
- **Website › Pages:** built-in pages are editable but can't be deleted. Header photo, SEO fields, demo flag. Slugs can't clash with app routes.
- **Website › News & events:** the review workflow (`PostWorkflow`). Content editors draft and submit; Manager/Owner/Admin publish, schedule, send back or archive. The nav badge counts posts in review.
- **Website › FAQs, Amenities** (drag to reorder). **Website › Redirects** with hit counter.
- **Front desk › Website enquiries:** list, view, update status. The nav badge counts new enquiries.
- **Bookings › Rooms & rates:** added photo gallery (media library, responsive WebP conversions) and in-room amenities.

### Packages added
`spatie/laravel-medialibrary` 11.23 + `filament/spatie-laravel-media-library-plugin` 5.11. Image conversions are **queued**, so run the queue worker (part of **Dev: all**). Pages fall back to the original image until conversions finish.

### Seeders
- `WebsiteContentSeeder` (all environments, never overwrites): 13 system pages (DEMO placeholder text except the communication policy, guest portal and contact intro) and 5 website settings.
- `DemoWebsiteSeeder` (local only): demo amenities, FAQs and 3 news posts.

### Tests
144 passing.
- Taxes and fees: M4 finance.
- Room readiness status (Dirty/Ready…): M8 housekeeping.
- Booking list and calendar UI: M4/M5.

## Milestone 4 — Reservations & finance (21–22 Nov 2026)

### Online booking (website)
`/check-availability` → **Book this room** → `/book` (extras, guest details, payment method with Instant EFT first, terms, optional marketing consent) → held booking + invoice + "payment needed" email → `/booking/{token}` (pay or EFT instructions) → payment provider → confirmation page and email with receipt.
- Holds use `booking.hold_minutes_online` (30-minute dev default, PEND-01). The guest sees the exact deadline.
- EFT is only offered once banking details are set in **Settings** (never invented). Proof of EFT sets **Pending verification** and does **not** confirm.
- Guest pages, invoice and receipt PDFs use unguessable 48-character tokens; no personal data in URLs.

### Payments
- `PaymentGateway` interface + **fake** driver (`config/payments.php`, refuses to run in production). In development the guest goes to `/dev/fake-gateway` with **Approve / Decline / Cancel**, which sends an HMAC-signed webhook through the real webhook pipeline.
- `WebhookProcessor`: signature check (401), each event once (duplicates → 200 "duplicate"), a late failure never undoes a success, amount mismatch → "Needs attention".
- `PaymentLedger`: only **Succeeded** counts as money. Updates booking payment status, confirms the booking once verified payments reach the deposit (`payments.deposit_percent`, 100% dev default, PEND-05), issues receipts.
- **Late payments (PEND-02):** money for an expired/cancelled booking is flagged. **Reinstate** re-claims the same room (or another of the type) if free, otherwise staff refund.
- **EFT verification:** Owner/Manager/Admin only, with the bank statement reference and amount. Reception records cash (received immediately) or EFT (pending).
- **Refunds (DEC-06):** one person requests, a different authorised person approves (also enforced by a DB CHECK). Approval refunds via gateway or records an EFT refund, issues a credit note and updates the booking.

### Documents
Separate tables for quotations, invoices, receipts, credit notes. Gap-free numbers per type (`INV-000001`, `REC-…`, `QUO-…`, `CRN-…`). Issued documents and their lines cannot be edited or deleted (`ImmutableDocumentException`). Quotation → invoice conversion without retyping. VAT only added if `payments.vat_rate_percent` is set (VERIFY). PDFs via dompdf with the built-in Helvetica font (~2 KB each), DEMO watermark outside production.

### Guests
Profiles with encrypted email/phone and keyed lookup hashes; returning guests matched by email or phone (BOOK-06). SA phone numbers stored as +27…. Search by name or exact email/phone in admin.

### Admin
- **Bookings › Bookings:** list (default: active bookings), view (rooms, extras, money, history), **New booking** (front desk: specific room optional, staff-chosen hold expiry), buttons: Record payment, Reinstate, Issue quotation, Cancel (reason), Confirm without payment (**Owner only**, PEND-06).
- **Bookings › Extras** (breakfast, dinner…).
- **Front desk › Guests, Payments** (queue of EFTs to verify + exceptions), **Refunds** (approve/reject; approve hidden from the requester).

### Settings added (Admin › Settings)
`payments.deposit_percent`, bank name/account name/number/branch (empty), `payments.vat_number`, `payments.vat_rate_percent` (empty, VERIFY), `payments.invoice_due_days`.

### Demo data
`DemoAccommodationSeeder` now also adds "Demo breakfast" (R150) and "Demo dinner" (R250) per guest per night.

### Checked in the browser
Full flow on the local server: booking BL-U5KXFA (Demo Standard Room + breakfast, R2 400) → fake Instant EFT → confirmed, "Verified paid", invoice INV-000001 and receipt REC-000001 downloadable. Found and fixed: fake provider page buttons invisible until `npm run build`; PDFs ~880 KB (now ~2 KB); return page lost its flash message.

### Not yet (by design)
- Real payment provider (PEND-07); coupons/discounts; booking amendments (date changes, extensions — M5); guest folio and charge-to-room (M7/M10); WhatsApp/SMS messages (M6). Emails currently go to the `log` mailer.
- PDF layout not yet checked visually by a person.

### Tests
186 passing.

## Milestone 5 — Reception (23 Nov 2026)

### Built
- **Check in** (booking screen, green button on/after the arrival day, confirmed bookings only): guest register form (full name, ID type/number, nationality, address, vehicle, guests). Required fields come from the setting `register.required_fields` (default: full name only, **VERIFY** with a legal adviser). ID number and address are encrypted; the screen shows the ID masked (`•••••9087`). Optional key handover is logged. A document QR code is created.
- **Check out:** zero-balance gate (CHK-02). If money is owed only the **Owner** can override, with a reason recorded in the booking history (PEND-06). Warns about keys not returned. (Housekeeping turnover = M8, farewell message = M6.)
- **Front desk menu:** **Extend stay** (claims the extra nights in the same room, supplementary invoice; refuses if the room is taken), **Move room** (whole stay, before check-in; uses `ReassignRoom`, reason audited), **Add charge** (own invoice), **Log key / items / complaint**.
- **More menu:** **Replace document QR code** (old printed QRs stop working).
- **Front-desk log tab** on each booking; complaints and lost keys stay "Open" until resolved.
- **Money across invoices:** balance = all non-voided invoices − credit notes − verified payments (`Booking::balanceDue()`), used everywhere.
- **Document QR (REC-06/07):** invoices and receipts carry a QR with `/admin/scan?code=…` (opaque 32-character code, no personal data). **Front desk › Scan document** accepts a USB/Bluetooth scanner, typed code or the camera (jsQR, decoded in the browser). Every scan is in the audit log.
- **Front desk › Room calendar (REC-02):** 14-night grid from the room-night ledger, colour-coded (hold, confirmed, in house, checked out, blocked), click a stay to open it.
- **Dashboard (REC-01):** arrivals/departures today, in house, free rooms tonight, guests owing money, EFTs to verify + payment problems, holds expiring in 2 h, open complaints/lost keys, rooms out of service; tables of arrivals, departures and in-house guests. Refreshes every 60 s.
- **Front desk › Offline arrivals sheet (OFF-01..03):** printable sheet of today's and tomorrow's arrivals and in-house guests (no phone, email or ID). Chosen instead of a browser cache because reception devices are shared and unmanaged; OFF-03 allows this fallback. No bookings or payments offline (DEC-08).

### Packages added
`bacon/bacon-qr-code` 3.1 (QR images via GD), `jsqr` 1.4 (npm, camera decoding).

### Not yet / limits
- Moving a guest **partway** through a stay (after check-in) — later; today Move room is for the whole stay before arrival.
- Checking the new admin screens in a browser needs a staff sign-in with 2FA, so they were tested with Livewire tests only.

### Tests
215 passing (29 new for reception).
