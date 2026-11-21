# Implementation progress

New session? Read `docs/MASTER_PROMPT.md`, `docs/FINAL_SPECIFICATION.md` and this file, then continue from **Next**.

## Current status

- **Milestone 1 — Foundation:** done.
- **Milestone 2 — Rooms & booking engine:** done (engine and admin screens). There is no booking UI yet: bookings are created through `PlaceBooking`, and the screens come in M4/M5.
- **Milestone 3 — Public website:** done (all 20 pages, CMS, SEO, redirects, enquiries).
- **Milestone 4 — Reservations & finance:** done (online booking + fake payment provider, guest profiles, extras, quotations/invoices/receipts/credit notes, EFT verification, dual-approval refunds, admin screens).
- **Milestone 5 — Reception:** done (check-in/out, guest register, extend stay, charges, front-desk log, document QR + scanner, room calendar, dashboard, offline arrivals sheet).
- **Milestone 6 — Messaging & WhatsApp bookings:** done (SMS/WhatsApp messaging with log drivers, templates, automations, consent, spend cap, WhatsApp price lists).
- **Milestone 7 — Guest portal:** done (room QR + SMS code sign-in, requests, room-service menu, reception inbox with chime, escalation, bill and self-checkout, reviews with moderation, extra phones).
- **Milestone 8 — Housekeeping & maintenance:** done (tasks, checkout turnover, Monday deep clean, inspections with rework, room readiness, maintenance and equipment, stock ledger, lost property).
- **Milestone 9 — Employees & attendance:** done (profiles with approval, departments, weekly rosters, leave, holidays, cover, raw punches + derived attendance, CSV import, manual entry, corrections, monthly export; Hikvision behind an interface).
- **Milestone 10 — Leisure & food:** done (service catalogue with hours, slots and capacity, spa therapist + room checks, reception and portal bookings, charge-to-room or walk-in payment with invoice/receipt, meal ordering hours, kitchen tickets, feature flags).
- **Milestone 11 — Reporting:** done (owner dashboard with documented metrics and date bases, 17:00 daily report with stored snapshot, email, push (log driver), delivery log and retries, report archive, day close, campaign tracking).
- **Milestone 12 — External channels:** done as far as possible without a provider (ChannelManager interface + null driver, idempotent OTA import with room assignment or exception queue, sync log and stale warnings, scheduled sync, iCal export/import fallback).
- **Next:** **Milestone 13 — Final quality** (compliance config, security, backup restore, performance, accessibility, full tests, production checklist; FINAL_SPECIFICATION §22).

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

## Milestone 4 — Reservations & finance (21 Nov 2026)

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

## Milestone 5 — Reception (21 Nov 2026)

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

## Milestone 6 — Messaging & WhatsApp bookings (21 Nov 2026)

### Messaging (MSG-01..06)
- `MessageProvider` interface per channel (SMS, WhatsApp) with a **log driver** (`config/messaging.php`): messages are stored and written to the log, **never sent**, until providers are chosen (PEND-07).
- `Messenger` is the only way to send. Every attempt is a row in `messages` (recipient encrypted), including ones deliberately not sent, with the reason: missing value, guest opted out of the channel, no marketing consent, monthly cap reached, template switched off.
- **Idempotency:** every automated message has a key like `payment_confirmed:42`; the same key never sends twice.
- **Retries:** `SendMessage` job, 3 tries (1 min, 5 min), then **Failed** with the error. Staff can **Resend**, on the same or the other channel.
- **Spend cap (MSG-06):** per channel per month (`messaging.monthly_cap_*_cents`, empty = no cap). Owner emailed once a month at 80%. At 100% only **critical** messages (payment confirmation) go out.
- **Templates:** 9 messages × WhatsApp + SMS (`MessageTemplateSeeder`, all environments, never overwrites edits). Editing the wording bumps the version; unknown placeholders are rejected. Cost estimates start at R0.
- **Channel:** `messaging.preferred_channel` (WhatsApp, dev default). Guests can be opted out per channel on their profile.

### Automations (MSG-03)
| Message | When |
|---|---|
| Booking received / how to pay | When a held booking is created (website or front desk) |
| Unpaid hold reminder | Hold ends within 10 minutes (bookings at least 10 minutes old) |
| Payment confirmed | Booking confirmed (with the existing email + receipt) |
| Pre-arrival information | 2 days before arrival, from 08:00 |
| Departure day | Morning of departure, from 08:00 |
| Thank you / safe travels | At check-out |
| Feedback request | Day after check-out, **only if** `messaging.feedback_request_enabled` = yes (off) |
| Price-list follow-up | Unused price list after `price_lists.follow_up_hours` (4) |
Scheduled: `messages:run-automations` every 5 minutes (safe to repeat).
Not yet: arrival-day guest-portal link (M7), staff task notices (M8), owner escalation (M11).

### WhatsApp price lists (WA-01..06)
- **Bookings › WhatsApp price lists › New:** dates, guests, optional name and WhatsApp number. Creates a secure link `/p/{token}` valid for `price_lists.valid_hours` (24, dev default) and sends it through the messaging system. **Send from WhatsApp** opens WhatsApp (click-to-chat) with the text ready, so reception can send it from the lodge's own WhatsApp today, with no API.
- Guest page: live availability and prices for those dates, **Choose this room** → normal booking flow, recorded as source **WhatsApp** and linked to the price list. Opening the link never holds a room; expired or cancelled links show a friendly 410 page.

### Admin
Front desk › **Messages** (log, failures badge, monthly spend shown, Resend), booking **Messages** tab, Settings › **Message wording**, Bookings › **WhatsApp price lists**, guest profile **Do not message on**.

### Other
- `phpunit.xml` memory limit raised to 512 MB (suite outgrew 128 MB).
- `.claude/launch.json` starts the site on port 8001 from the Claude app.

### Not yet
- Real SMS/WhatsApp providers, delivery receipts from providers, inbound "STOP" handling (need a provider).
- "Reception notified" on payment (WA-05) is via the dashboard/Payments badge; push/in-app alerts come with M11.

### Tests
244 passing (29 new). Price-list page checked in the browser.

## Milestone 7 — Guest portal (21 Nov 2026)

### Signing in (GST-01..06)
- Each room has a printable QR (`/stay/r/{32 random chars}`) that identifies the **room only**. Admin › Accommodation › Rooms › **Room QR** shows it; **Replace QR** rotates it and signs out everyone who used the old one.
- The guest enters their mobile number; a 6-digit code is sent by SMS **only if** the number belongs to the stay in that room (the booking phone or a number the booker added). The reply is identical either way (no occupancy or number enumeration).
- Codes: 5 minutes, 5 tries, at most 3 sends per stay per 15 minutes, plus per-device rate limits; an alert is logged at 30 codes/hour (SMS pumping). The code is hashed; the message log stores it as `••••••`. (The **log** SMS driver writes the real text to the Laravel log, which is how you get the code in development.)
- Other ways in: the signed link in the new **portal welcome** message at check-in (valid until the day after departure), or a 30-minute link reception issues from the booking (**Front desk › Guest portal link (after ID check)**) with the identity check recorded in the audit log.
- A session ends at check-out, room move, QR replacement, removal of an added phone, or **End guest portal access** on the booking.

### For the guest (`/stay`)
Home (stay info from settings — Wi-Fi, hours, emergency contacts; empty settings are hidden), **Requests** (towels, toiletries, help, other), **Menu** (room service; charge to room or pay on delivery), **Reception** messages, **Bill** with self-checkout when nothing is owed (otherwise reception gets a checkout request), **Review** (one per stay, optional photos, publish only with consent), **Other guests in the room** (booker only).

### For staff (`/admin`)
- Front desk › **Guest requests** (On it / Done / Cancel; refreshes every 15 s, red badge) and **Guest messages** (reply in a conversation modal). A **chime and pop-up** play on every admin page when something new arrives. Dashboard stat **Guest portal waiting**.
- Room service is billed when reception marks the order **Done** — once, on a supplementary invoice.
- Escalation (`portal:escalate`, every minute): untouched requests/unanswered messages go to managers after `escalation.backup_minutes` (10) and to the owner after `escalation.owner_minutes` (30), by email.
- Front desk › **Guest menu** (switch items off instead of deleting). Website › **Reviews** (publish/unpublish/reject; publishers only). Published reviews show on the homepage.

### Settings / seeders
`portal.welcome`, `portal.wifi_name`, `portal.wifi_password`, `portal.facility_hours`, `portal.emergency_contacts` (PENDING, PEND-04). Template `portal_welcome`. `DemoMenuSeeder` (local only, 5 items marked DEMO).

### Not yet
Payment from the portal (pay links come with a real provider), push notifications (M11), photos on public reviews.

### Tests
289 passing (45 new in `tests/Feature/Portal`). Portal sign-in link, home, menu, requests and bill checked in the browser at phone width.

## Milestone 8 — Housekeeping & maintenance (21 Nov 2026)

### Tasks (HK-01..03)
- One `tasks` table for all 14 task types in HK-01, with room / equipment / place, assignee, priority, due time, checklist, notes, completion notes, photos, cost and supplier.
- **Who assigns (HK-03):** reception, managers and the owner create and assign housekeeping (daily clean, light refresh, checkout clean, deep clean); only managers and the owner do gardening and maintenance types. Housekeeping tasks can only go to housekeeping staff (or a manager/owner).
- **Finishing (HK-02):** only the assigned worker can start or finish. Every checklist item must be ticked, or a note written when there is no checklist. Up to 3 photos (private disk, EXIF stripped).
- Workers are emailed when a task is given to them. Anyone on the operations team can **Report a fault** (creates a Repairs task and emails managers/owner).
- Standard checklists per task type (**Settings › Task checklists**). Only the Monday deep-clean list is seeded — it is the one in SPEC v5.3 (HK-04); the others start empty until the lodge supplies them.
- **Cleaning times** per room type and task (HK-07) are shown on tasks; they will feed rosters in M9. None are seeded (not invented).

### Checkout turnover (CHK-03/04)
At checkout (reception or portal) a queued job sets each room to **Dirty** and creates one **checkout clean** (High priority if another guest arrives in that room today). The job uses an idempotency key, so re-runs never duplicate the task or set a room back. Locally the queue is `database`: run a queue worker (`php artisan queue:work`, or `composer run dev`) for checkout tasks to appear.

### Inspections and room readiness (HK-05/06)
- Rooms have a cleaning status: Dirty → Being cleaned → Awaiting inspection → Ready. Every change is in the audit log.
- Finishing a checkout or deep clean opens an inspection. Inspectors (or a manager/the owner) score each checklist item 0–5; any item below 4 fails (`inspections.scale_max` / `pass_mark`, PENDING PEND-15). Nobody can inspect their own clean.
- A failure sets the room back to Dirty and creates a **rework** task for the same worker with only the failed items (High priority, emailed). Finishing rework opens a re-inspection.
- **Owner only** can mark a room Ready without an inspection, with a reason (PEND-06, audited). Check-in shows a warning when the room isn't Ready (it does not block).
- **Operations › Room status** board.

### Schedules, maintenance and stock
- **Monday deep clean (HK-04):** `housekeeping:deep-cleans`, Mondays 06:00, one per active room, safe to repeat.
- **Recurring jobs (HK-08):** `maintenance:run-schedules` daily 06:05 creates the task on the due date and moves the schedule on (catch-up creates one task, not a backlog). **Equipment** register with warranty and job history.
- **Stock (HK-09):** ledger of movements (received / used / damaged / correction); on-hand is always the sum and can't go below zero. Corrections are manager/owner only, with a reason; movements can't be edited or deleted. Owner/managers are emailed once when an item hits its reorder level (again only after a restock).
- **Lost property (HK-10):** Front desk › Lost property; front desk, managers and owner only. Closing an entry records who and when.

### Screens
- `/admin` › **Operations:** Tasks, Room status, Inspections, Equipment, Recurring jobs, Stock. **Front desk:** Lost property. **Settings:** Task checklists, Cleaning times.
- `/staff`: **My tasks** (start, checklist, photos, done, report a fault), **Inspections** (inspectors, managers, owner), **Stock** (record used/received/damaged).

### Not yet
Rosters, shifts and fair task allocation from the cleaning times (M9); push notifications (M11); an antivirus scanner for uploads (interface still pending, SEC-08).

### Tests
322 passing (33 new in `tests/Feature/Housekeeping`). The `/staff` and new `/admin` screens are covered by Livewire tests; not yet clicked through in a browser (needs a staff sign-in).

## Milestone 9 — Employees & attendance (21 Nov 2026)

### Employees (EMP-01, EMP-03)
- **People › Employees:** profile linked to a login, department, job title, start/end dates, photo, phone and emergency contact (encrypted), and the person's ID on the clock terminal. New profiles start as *Waiting for approval*.
- Staff edit their own details in `/staff` › **My details** (name they go by, phone, emergency contact, photo). Changes wait until the owner or a manager approves them; managers can't approve their own.
- HR screens are for the **owner and managers only** (not reception or the administrator). Staff see only their own shifts, leave and attendance.

### Rosters (EMP-02)
- **Shifts** with clock-in/out windows (opens before start, late-after grace, closes after end). A shift ending before it starts runs past midnight.
- **Weekly roster** (pick several weekdays at once), **Roster changes & cover** (a different shift, a day off, or covering for someone), **Public holidays** (entered by the lodge, not seeded) and **Leave** (staff ask in `/staff` › My leave; owner/manager approve or decline with a reason).
- Precedence for a day: roster change → approved leave → public holiday (if that roster line doesn't work holidays) → weekly roster → off.
- **Roster (week view)** page; `/staff` › **My shifts** shows the next 14 days.

### Attendance (ATT-01..05)
- **Raw punches** are stored as received and can't be edited or deleted. Each has a composite dedup key (source + device + person + time + direction), so importing the same file twice adds nothing.
- Sources: **CSV import** (People › Attendance › Import clock CSV), **manual punch** (owner/manager, with a reason, audited) and a **Hikvision** adapter that deliberately refuses to run until the on-device test in `docs/HIKVISION_POC.md` passes. Punches for an unknown terminal ID are kept and linked when that ID is entered on an employee.
- **Derived days**, worked out hourly (`attendance:derive`) and after every import: Worked, Late, Absent, Incomplete, Off duty, Pending review. A rostered day is decided only after its clock-out window closes. **Off-duty staff are never marked absent**; punches on a day off become *Pending review*.
- **Corrections** (owner/manager, not your own): status and times with a reason; before/after go to the audit log and the day is locked against recalculation.
- **Export month (CSV)**: hours and statuses per employee per day, ready for payroll. No pay is calculated (DEC-09). Cells that look like spreadsheet formulas are escaped.

### Notifications (EMP-04)
Non-urgent staff emails (task assigned, low stock, leave decisions…) are not sent to someone who has an employee profile and is off duty at that moment. Urgent ones (urgent tasks and faults) always go.

### Demo data
`DemoStaffSeeder` (local only): employees for the demo receptionist, housekeeping, maintenance and inspector logins, with two **DEMO** shifts on weekdays.

### Not yet
Hikvision connection (needs the device), push notifications (M11), leave balances and pay (out of scope, DEC-09), retention/anonymisation of attendance records (PEND-11, M13).

### Tests
364 passing (42 new in `tests/Feature/Staff`). New screens covered by Livewire tests; not clicked through in a browser (needs a staff sign-in).

## Milestone 10 — Leisure & food (21 Nov 2026)

### Services (LSR-01/02)
- **Leisure › Services:** name, category (pool/day pass, braai, VIP area, spa, meal, other), price per person or per booking, duration, slot interval, minimum notice, opening hours per weekday, capacity (people, or bookings for e.g. one group per VIP area), largest group, guest cancellation cut-off, restrictions text, and switches for portal booking, charge-to-room and the safety acknowledgement.
- Times offered are only free slots inside opening hours. Capacity is checked under a row lock, so two receptionists can't overfill a slot.

### Spa (LSR-03)
**Leisure › Therapists & rooms.** A spa service needs one free therapist **and** one free treatment room; the hold is protected by a database exclusion constraint (like room inventory), so a therapist or room can never be double-booked. Cancelling or a no-show frees them.

### Bookings and payment (LSR-02, LSR-05)
- **Leisure › Leisure bookings:** reception books for in-house guests or walk-ins (only free times are listed).
- **In-house guests** can charge eligible services to the room; the charge is added once, when reception marks the booking **Done**.
- **Walk-ins** pay at reception first (**Take payment**): an invoice without a room booking is issued, and cash gets a receipt at once; EFT waits for verification on the Payments screen as usual.
- Guests may cancel in the portal until the service's cut-off; reception can cancel any time with a reason. Paid bookings must be refunded through the payment first. No-shows can be recorded after the start time; **no fees are charged** until the lodge sets a policy (PEND-05).
- **Guest portal › Book:** in-house guests choose a service, date and group size, pick a free time, and see/cancel their bookings.

### Meals and the kitchen (LSR-04)
- **Leisure › Meal ordering hours:** per menu category (e.g. Breakfast 18:00–07:30 overnight). Orders outside the window are refused with the window shown; the portal menu shows the hours.
- Every room-service order and every booked meal creates a **kitchen ticket** with the allergens copied from the menu. New `Kitchen staff` role; `/staff` › **Kitchen** refreshes every 15 s: Start → Ready (the guest then sees "On its way"). Room-service tickets close when reception marks the order Done (which is when it's billed); meal bookings are marked Served in the kitchen.

### Feature flags (LSR-06, PEND-14)
`features.indemnity`, `features.vouchers`, `features.packages`, `features.event_hire` — all **off**. Only the indemnity acknowledgement is built (records the acceptance time on the booking); vouchers, packages and event hire are not built.

### Demo data
`DemoLeisureSeeder` (local only): DEMO pool day pass, braai area, VIP 1, VIP 2, 60-min spa massage, dinner, one therapist and one treatment room. Names follow the spec; all prices, hours and capacities are placeholders.

### Not yet
Online self-booking and payment for day visitors on the public website (they book through reception or WhatsApp for now); no-show / late-cancellation fees (PEND-05); vouchers, packages, event hire (PEND-14).

### Tests
392 passing (28 new in `tests/Feature/Leisure`). Portal Book page checked in the browser at phone width.

## Milestone 11 — Reporting (21 Nov 2026)

### Owner dashboard (REP-01, REP-02, REP-06)
**Reports › Owner dashboard** (owner and managers): today, yesterday, last 7 days, this month, last month or a custom range (up to a year).
- **Rooms (stay date):** occupancy, room-nights sold/available, room revenue, ADR, RevPAR, nights blocked; stays split into confirmed / pending / cancelled / no-show / expired.
- **Money (transaction date):** payments received (and by method), refunds paid, invoiced, credit notes, leisure done, EFTs awaiting verification, money owed now.
- **Operations:** arrivals/departures, in house, rooms by cleaning status, housekeeping, inspection score, maintenance and faults, complaints, guest requests waiting, attendance, exceptions (payments needing attention, failed messages/jobs/report deliveries).
- **Bookings and marketing (created date):** booking sources, online conversion, WhatsApp price-list conversion, enquiries, campaigns.
- Every section says its date basis; formulas are in `docs/REPORT_METRICS.md`. No industry benchmarks.

### Daily report (REP-03, REP-05)
- `reports:daily` runs every minute; from `reports.daily_time` (17:00 SAST) it stores that day's snapshot **once** (plus month to date and tomorrow's arrivals) and sends it to every active owner.
- **Email:** headline totals only, no guest details, and a link to the report that needs a sign-in.
- **Web push:** behind a `PushNotifier` interface with a **log driver**; deliveries show as *skipped* (never "sent") until a push provider and keys are approved.
- Every send is a delivery row (status, tries, last error). Failures are retried after 5, 10, 20, 40 minutes (5 tries). A missed 17:00 run catches up the next minute.
- Guest escalations stay on their own every-minute schedule (REP-05).

### Archive and day close (REP-04)
**Reports › Daily reports:** every stored day, with deliveries and a manual retry. **Close the day** (owner/manager, from 17:00 that day, once, audited) records the reconciled money totals without changing the 17:00 snapshot.

### Campaign tracking (REP-06)
The website remembers, for the visit, the first `utm_source` / `utm_medium` / `utm_campaign` (or the referring site's host name) and stores it on website bookings and enquiries. No full URLs, IPs or extra cookies.

### Not yet
Real web push (needs approval: package + VAPID keys), push for urgent staff alerts, charts (figures only for now), PDF export of reports.

### Tests
410 passing (18 new in `tests/Feature/Reports`). Dashboard and archive covered by Livewire tests; not opened in a browser (needs the owner's 2FA sign-in).

## Milestone 12 — External channels (21 Nov 2026)

Built behind interfaces so either PEND-08 path (custom Laravel + channel manager, or a hybrid PMS) stays open. **Nothing is connected**: no provider has been chosen (PEND-07/08).

### Channel manager interface (CHN-01)
`ChannelManager`: fetch reservations, push availability, push rates, and an honest `isConnected()`. Only the **null driver** exists. **Bookings › Channels** lists Booking.com and LekkeSlaap (DEC-03), created switched off; each has room-code mappings, iCal rooms and a sync log.

### Importing OTA reservations (CHN-03, DEC-02)
`ImportChannelReservation` is what a real driver will call:
- New reservation → booked through the normal booking engine, confirmed, **with a physical room assigned**, source Booking.com/LekkeSlaap, external reference kept. Priced at the channel's total when given (invoice + room revenue use it).
- The same reservation sent again changes nothing (unique per channel + reference).
- **No room free, unknown room code, changed dates, or a cancellation for a guest already in house** → nothing is overbooked or moved automatically; it goes to **Bookings › Channel problems** and reception, managers and the owner get an urgent alert. "Try again" re-places it (e.g. after freeing a room); otherwise resolve it with a note.
- Cancellations from the channel cancel the booking and free the room.

### Monitoring (CHN-04)
- `channels:sync` every 15 minutes for enabled channels; every attempt is logged (OK / failed with the error / **not connected**).
- A channel counts as synced only after a successful run through a connected driver; otherwise it shows **Stale** once past its threshold. The screens say plainly that no channel manager is connected — no real-time claims. Channel problems and stale channels show on the owner dashboard.

### iCal fallback (CHN-02)
Per channel and physical room, clearly labelled as limited (dates only, not a channel manager):
- **Export:** a secret `/ical/{token}.ics` link the channel can read (taken dates only; no prices or guests; nights that came from that channel's own calendar are left out to avoid echoes). Off by default; the link can be replaced.
- **Import:** upload or paste the channel's calendar. Dates taken there block the room here (reason "Booked on another channel"); removed or moved dates are released; repeats change nothing. A clash with one of our bookings is **not** double-booked — it becomes a channel problem with an urgent alert.
- Automatic fetching from the channel's link is built but **off** (`channels.ical_fetch_enabled`, PENDING PEND-07).
- Reports count iCal-blocked nights separately ("taken on other channels"), not as rooms out of service.

### Not yet
A real channel-manager/OTA driver (PEND-07/08), pushing availability and rates (interface only), payment details from OTAs (virtual cards etc.).

### Tests
425 passing (15 new in `tests/Feature/Channels`, including a fake connected driver).
