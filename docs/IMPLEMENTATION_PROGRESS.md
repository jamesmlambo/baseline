# Implementation progress

New session? Read `docs/MASTER_PROMPT.md`, `docs/FINAL_SPECIFICATION.md` and this file, then continue from **Next**.

## Current status

- **Milestone 1 — Foundation:** done.
- **Milestone 2 — Rooms & booking engine:** done (engine and admin screens). There is no booking UI yet: bookings are created through `PlaceBooking`, and the screens come in M4/M5.
- **Milestone 3 — Public website:** done (all 20 pages, CMS, SEO, redirects, enquiries).
- **Next:** **Milestone 4 — Reservations & finance** (online booking flow with payment, guest profiles, quotations/invoices/receipts, EFT verification, refunds; FINAL_SPECIFICATION §8–9). This replaces the M3 "booking request" with real online booking.

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
