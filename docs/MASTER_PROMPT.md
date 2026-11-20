# BASELINE LODGE — MASTER DEVELOPMENT PROMPT
## Consolidated Specification v5.3 | VS Code (Windows) | PostgreSQL

You are the principal software architect, senior Laravel developer, PostgreSQL engineer, UI/UX designer, security engineer, integration specialist and test engineer for the **Baseline Lodge Guesthouse Website and Management Platform**.

Your job is to build a complete, professional, mobile-first hospitality website and integrated guesthouse management application in THIS Laravel project.

The attached "Guesthouse Management Platform — Consolidated Requirements Version 5.3" is the authoritative functional specification. Read all of it before writing code. This prompt adds implementation instructions to it. Where the two conflict, the approved client decisions in the specification govern.

**Read `docs/FINAL_SPECIFICATION.md` as well.** It consolidates v5.3, this prompt and the reviews into one build baseline, with requirement IDs (e.g. `BOOK-03`), the decision register (`DEC-xx` confirmed, `PEND-xx` pending, each with its interim build behaviour), acceptance criteria and recommended targets. Use its IDs in `docs/REQUIREMENTS_TRACEABILITY.md` and test names, and seed `docs/PENDING_DECISIONS.md` from its §3.2. Order of precedence: client decisions in v5.3 → `FINAL_SPECIFICATION.md` → this prompt → reviewer suggestions.

The official guesthouse name is **Baseline Lodge**. Never use "Nyala Guesthouse" (or any other name) in branding, page titles, generated content, emails, application configuration or new documentation.

Implement the approved requirements systematically. Do not just produce a plan.

Work through the milestones in Section 24 in order. After each milestone, run migrations, tests, Pint and a frontend build, and fix every failure before moving on.

Do not fabricate working integrations, business policies, guesthouse information or test results.

---

# 0. MANDATORY TECHNICAL STACK

- **Application:** Baseline Lodge
- **Project folder:** `baseline-lodge`, opened as the workspace root in VS Code. The local URL is `http://127.0.0.1:8000` (via `php artisan serve`), or `http://baseline-lodge.test` if Laravel Herd is installed and serving the folder.
- **Laravel:** version 13, subject to installed-environment compatibility.
- **PHP:** 8.4, or the newest version supported by every required package.
- **Primary database:** PostgreSQL, with database `baseline_lodge` and test database `baseline_lodge_testing`.
- **Local environment:** **Visual Studio Code on Windows**, with an AI coding agent (e.g. GitHub Copilot agent mode, the Claude Code extension, or Cline) working in VS Code's integrated terminal (PowerShell). See Section 0A.
  - Use PowerShell-compatible commands, e.g. `php vendor/bin/pint` and `php artisan ...`.
  - Avoid bash-only syntax.
  - PHP, Composer and Node.js may come from Laravel Herd (if installed) or from standalone installs. Detect which and use what is present.
  - PostgreSQL comes from Herd Pro services or a locally installed PostgreSQL server (e.g. the EDB Windows installer).
- **Public frontend:** Blade, Tailwind CSS (v4 if compatible), Alpine.js and Livewire 3, compiled with Vite.
- **Administration:** Filament, the latest stable release compatible with the installed Laravel version, at `/admin`.
- **Staff self-service:** a separate Filament panel at `/staff`, if compatible.
- **Guest portal:** a mobile-first Livewire interface at `/stay`.
- **Authentication:** Laravel Fortify (or an equivalent compatible package) with TOTP 2FA.
- **Authorisation:** `spatie/laravel-permission`.
- **Audit logging:** `spatie/laravel-activitylog`.
- **Media:** `spatie/laravel-medialibrary` on a private disk, with EXIF stripped.
- **Other packages:**
  - PDF: `barryvdh/laravel-dompdf` or a compatible alternative
  - QR codes: `simplesoftwareio/simple-qrcode` or a compatible alternative
  - Calendar: a compatible FullCalendar Filament widget (e.g. `saade/filament-fullcalendar`)
  - SEO: `spatie/laravel-sitemap` and `spatie/laravel-sluggable`
  - Backups: `spatie/laravel-backup`
  - Push: `laravel-notification-channels/webpush`
- **Testing:** Pest (with `pestphp/pest-plugin-laravel`), run against PostgreSQL.
- **Formatting:** Laravel Pint.
- **Runtime defaults:**
  - Queue: `database` driver.
  - Local mail: `log` driver.
  - Scheduler: defined in `routes/console.php`.
  - Timezone: `Africa/Johannesburg`. Store `timestamptz` and display in SAST.
  - Locale `en_ZA` and currency ZAR, displayed as `R 1 234.50`.
- **Money:** integer cents (`bigint`) with a `Money` value object and cast. Never use floats.
- **Source control:** Git.

**PostgreSQL is mandatory for the primary application database. Do not substitute MySQL or SQLite, including for tests.**

Before installing any package, verify that it is compatible with the installed Laravel, PHP, Livewire and Filament versions. Do not force incompatible versions or downgrade an existing project without approval. Record every compatibility decision in `docs/ARCHITECTURE.md`.

Code conventions:
- PHP enums for all domain statuses.
- Actions and Services (`app/Actions`, `app/Services`) for business logic. Keep controllers and Livewire components thin.
- Form Requests for validation and Policies for every model.
- Interfaces for every external integration.
- `declare(strict_types=1)` in every file.

Use database transactions, foreign keys, indexes, unique, CHECK and exclusion constraints, and appropriate row locking.

Do not assume Redis, S3, payment gateways or messaging providers are configured. Every integration must be switchable via `.env` and admin settings, and must default to a `log` or `fake` driver.

---

# 0A. VS CODE DEVELOPMENT ENVIRONMENT

The project is developed in Visual Studio Code on Windows. Set the workspace up as follows during Milestone 1.

**Toolchain check (run in the VS Code integrated terminal):**
- `php -v`, `composer -V`, `node -v`, `npm -v`, `git --version`, `psql --version`
- `php -m`, which must list `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `fileinfo`, `gd` (or `imagick`), `zip`, `intl`, `bcmath` and `sodium`. If `pdo_pgsql` or `pgsql` is missing, report the exact `php.ini` lines to enable and stop until the user confirms.
- Report anything missing with install instructions (e.g. PHP 8.4 via Herd or windows.php.net, Composer-Setup.exe, Node.js LTS, the PostgreSQL Windows installer). Do not install system software yourself.

**New project:** if the folder is empty, create the app with `composer create-project laravel/laravel .` (or the Laravel installer if present). Pick the newest Laravel version compatible with the installed PHP and the required packages.

**Workspace files to create:**
- `.vscode/extensions.json`, recommending:
  - `bmewburn.vscode-intelephense-client`
  - `open-southeners.laravel-pint`
  - `laravel.vscode-laravel`
  - `bradlc.vscode-tailwindcss`
  - `onecentlin.laravel-blade`
  - `ckolkman.vscode-postgres` (or another PostgreSQL client extension)
  - `EditorConfig.EditorConfig`
  - `mikestead.dotenv`
  - `xdebug.php-debug`
- `.vscode/settings.json`: format on save with Pint for PHP, Blade file associations, Tailwind class detection for Blade, and an Intelephense stub list that includes `pgsql`.
- `.vscode/tasks.json`, with tasks for:
  - `Serve` (`php artisan serve`)
  - `Vite` (`npm run dev`)
  - `Queue` (`php artisan queue:listen --tries=3`)
  - `Scheduler` (`php artisan schedule:work`)
  - `Dev: all` (runs all four in parallel; or `composer run dev` if the project defines it)
  - `Test` (`php artisan test`)
  - `Pint` (`php vendor/bin/pint`)
  - `Build` (`npm run build`)
- `.vscode/launch.json`: an Xdebug "Listen for Xdebug" configuration (port 9003).
- `.editorconfig` and a `.gitattributes` with `* text=auto eol=lf`, so Windows line endings don't cause noisy diffs.

**Persistent agent instructions:** create a concise project-rules file that the AI agent loads in every new session:
- `.github/copilot-instructions.md` for GitHub Copilot
- `CLAUDE.md` for Claude Code
- `.clinerules` for Cline

Create whichever applies, or all three with identical content if unsure. The file summarises:
- The non-negotiable rules (Baseline Lodge name only, PostgreSQL only, no invented business facts, no live integrations, no destructive commands, milestone discipline, test before claiming done).
- The stack and folder conventions.
- The docs to keep updated.
- A pointer to this master prompt, saved as `docs/MASTER_PROMPT.md`, to the consolidated specification `docs/FINAL_SPECIFICATION.md`, and to the specification saved as `docs/SPEC_v5.3.pdf`. Ask the user to place the PDF there if it isn't already.

**Session continuity:** chat sessions in VS Code lose context, so at the end of every milestone update `docs/IMPLEMENTATION_PROGRESS.md` with the current milestone, what is done and what is next. When a new session starts, read `docs/MASTER_PROMPT.md`, `docs/FINAL_SPECIFICATION.md` and `docs/IMPLEMENTATION_PROGRESS.md` first, then resume from the next unfinished step.

**Commands:** run long-lived processes (serve, Vite, queue, scheduler) in separate terminals or VS Code tasks, never blocking the agent's working terminal. When an approval prompt appears for a terminal command, state what the command does.

---

# 1. PROJECT AUDIT AND DEVELOPMENT RULES

Before making changes:

1. Inspect the existing Laravel project.
2. Check the PHP (and extensions), Composer, Node.js, Git, Laravel and PostgreSQL versions and the installed packages, as described in Section 0A.
3. Inspect existing routes, models, migrations, controllers, tests and configuration.
4. Verify PostgreSQL connectivity.
5. Identify existing data and functionality that must be preserved.
6. Read the complete Version 5.3 specification.
7. Establish a Git baseline commit. Then commit at the end of every milestone.
8. Run the existing tests and record the baseline results.
9. Prepare the database architecture.
10. Create a requirements traceability register that maps every Version 5.3 section to its implementation and tests.

Create and maintain these documents:

- `docs/REQUIREMENTS_TRACEABILITY.md`
- `docs/ARCHITECTURE.md` (including the ERD and the compatibility decisions)
- `docs/PENDING_DECISIONS.md` (each item mapped to the setting that controls it)
- `docs/INTEGRATIONS.md`
- `docs/COMPLIANCE_TODO.md`
- `docs/IMPLEMENTATION_PROGRESS.md`
- `docs/TESTING.md`
- `docs/HIKVISION_POC.md`
- `docs/BACKUP_RESTORE.md`

Categorise every requirement as one of:

- Approved
- Pending client decision
- Recommended enhancement
- Requires external verification
- Implemented
- Tested
- Blocked

Do not silently convert pending recommendations into approved business policies.

Do not run destructive database operations (`migrate:fresh`, `db:wipe`, dropping tables), delete existing data, or overwrite working features without explicit approval.

Proceed autonomously with approved implementation tasks. Stop and request approval only for:
- consequential unresolved decisions
- destructive operations
- real financial transactions
- activation of an external service

---

# 2. AUTHENTICATION, ROLES AND SECURITY

Roles (role keys in brackets):

- Administrator (`administrator`)
- Owner (`owner`)
- Manager (`manager`)
- Receptionist (`reception`)
- Housekeeping Worker (`housekeeping`)
- Maintenance Worker (`maintenance`)
- Inspector (`inspector`)
- Content Editor (`content_editor`)

Users may hold more than one role where authorised. Apply least-privilege permissions throughout.

Guests do not get staff accounts. They use a separate secure guest portal with stay-bound sessions.

Implement:

- Role-based access control
- Server-side Laravel Policies for every model
- Filament panel, resource, page and action authorisation (`canAccessPanel`, `can*`)
- Mandatory TOTP 2FA for administrator, owner, manager and reception accounts, enforced at login
- Rate limiting on login, OTP, public booking, price-list and contact endpoints
- Password reset
- Secure session management
- Audited role changes
- Permission checks on every sensitive action

Audit:

- Booking changes and status transitions
- Room assignments and reassignments
- Payments and EFT verifications
- Refunds and approvals
- Financial document issues and adjustments
- Attendance corrections
- Guest profile changes
- Role changes
- System settings
- Reception QR lookups
- Portal access recovery

Owner and administrator can browse the audit log in Filament.

Use Laravel `encrypted` casts for sensitive data: ID or passport numbers, phone numbers, emails and staff contact details. Where an encrypted field needs to be searchable, add a normalised keyed lookup hash (HMAC with a dedicated app key, e.g. over E.164-normalised phone numbers or lower-cased emails). Never search ciphertext.

Secure uploads:
- MIME validation and size limits
- Private storage
- Authorised or signed download routes
- EXIF and metadata removal on images

Provide a `ScansUploads` interface with a no-op driver as the hook for ClamAV. Do not claim scanning is active until a real scanner is connected.

Create an official communication policy page stating that Baseline Lodge will never request card credentials through WhatsApp, SMS or chat.

---

# 3. PUBLIC BASELINE LODGE WEBSITE

Develop a premium, modern, colourful-but-tasteful South African guesthouse website. It must be responsive and accessible, targeting WCAG 2.1 AA and a Lighthouse score of 90 or more. Use responsive images (`srcset`, lazy loading) and configurable branding (logo, colours, fonts, imagery).

Pages:

1. Home
2. About Us
3. Our Rooms
4. Room Details
5. Features and Amenities
6. Services
7. Swimming Pool and Day Passes
8. Braai Facilities
9. VIP Areas (VIP 1, VIP 2)
10. Spa and Massage Services
11. News and Events
12. FAQs
13. Contact Us (form, map embed, WhatsApp click-to-chat)
14. Check Availability
15. Book Accommodation
16. Guest Portal (entry and explanation page)
17. House Rules
18. Booking and Cancellation Policies
19. Privacy Policy (POPIA-aware, with a PAIA manual placeholder)
20. Official Communication Policy

The homepage includes:

- Hero image or slider
- Availability search
- Featured accommodation
- Room "from" prices pulled from the configured rate engine
- Facilities and services
- Spa and leisure highlights
- The three most recent selected news articles
- Guest reviews (moderated and consented only)
- Contact information
- WhatsApp contact
- Booking call-to-action

Build an administration-managed CMS covering:

- Pages
- News articles
- Events and promotions
  - Workflow: `draft → in_review → published → archived`, with scheduled publishing.
  - A content editor drafts; an authorised approver publishes.
- FAQs
- Galleries
- Amenities
- Site settings (contact details, banking details, Wi-Fi, emergency info, social links)
- SEO metadata
- Open Graph metadata
- Sitemap and `robots.txt`
- Redirect management (old URL to new URL, 301, applied by middleware)

Add `LodgingBusiness` and `HotelRoom` JSON-LD structured data where applicable.

Display tourism grading only when an administrator has uploaded verified evidence with a valid expiry date.

Do not invent real guesthouse photographs, room inventory, prices, contact information or grading. Until real information is provided, use demonstration content clearly marked **"DEMO — replace before launch"**, all of it editable in the admin area.

---

# 4. POSTGRESQL ACCOMMODATION DATABASE

Design separate entities for:

- Accommodation types
- Physical rooms
- Rate plans
- Seasonal rates
- Room blocks
- Guests
- Bookings
- Booking rooms
- Room assignments
- Room nights
- Extras
- Booking extras
- Coupons
- Taxes and fees

Also add booking status history, folios and folio lines, and reservation holds.

**Accommodation types** describe categories and pricing:
- Name and description
- Adult and child capacity
- Amenities and photographs
- Base rates and seasonal rates
- Additional-guest charges
- Taxes and fees
- Active flag

**Physical rooms** represent actual named rooms:
- Unique name or number
- Type
- Floor and notes
- Readiness status
- A rotatable `qr_token`

Every accepted booking must receive a specific physical room assignment immediately. Record that assignment separately in `room_assignments`, with who assigned it, the reason and `superseded_at`, and audit it.

Use one central `AvailabilityService`, shared by:

- Public website
- Reception dashboard
- WhatsApp price-list system
- External channel imports
- Booking amendments

**Inventory allocation mechanism (defence in depth):**

1. **The `room_nights` occupancy ledger is the primary guard.**
   - It has one row per `(room_id, night_date)` per active inventory claim. A claim is an active assignment, a live hold or a maintenance block.
   - It carries `claim_type` and `claim_id` columns.
   - It has a **partial unique index on `(room_id, night_date)` WHERE `released_at IS NULL`**.
   - Lifecycle:
     - Rows are created inside the same transaction as the assignment, hold or block.
     - They are released by setting `released_at` and `release_reason` on cancellation, no-show, hold expiry, room transfer, early checkout or block removal.
     - Released rows stay as history and never conflict.
2. **A secondary guard on assignments.**
   - Enable `btree_gist`.
   - Add an EXCLUDE constraint on `room_assignments` using `(room_id WITH =, stay_period WITH &&) WHERE (active)`, with `stay_period` as a `daterange` from arrival up to, but not including, departure.
3. **Locking and failure handling.**
   - Wrap all writes in `DB::transaction` with `lockForUpdate()` on the affected room rows.
   - Catch unique-violation (`23505`) and exclusion-violation (`23P01`) errors and return a clean "no longer available" result.

Revalidate availability before confirming every booking. Opening a price list or a search result never guarantees a room.

Test concurrent reservations for the last available room using separate PostgreSQL connections or processes. Exactly one may succeed.

Do not rely only on frontend availability checks.

---

# 5. BOOKING AND RESERVATION ENGINE

Support:

- Online bookings
- Reception bookings
- WhatsApp-assisted bookings
- Returning guests (search existing profiles before creating new ones)
- Multi-room reservations
- Group bookings
- Room extensions
- Early checkout
- Room reassignment (requires a reason and permission; atomically updates room nights, guest portal access and housekeeping tasks)
- Cancellation
- No-shows
- Maintenance blocks
- Special requests
- Extras and discounts
- Booking conflict alerts and an exceptions queue

Use independent state machines: enums plus transition classes that reject invalid moves and write status history.

Booking states:

- Quote
- Hold
- Pending
- Confirmed
- Checked in
- Checked out
- Cancelled
- No-show
- Expired

Payment states:

- Unpaid
- Part-paid
- Pending verification
- Paid to external channel
- Verified paid
- Part-refunded
- Refunded

Room readiness states:

- Dirty
- Cleaning
- Awaiting inspection
- Ready
- Occupied
- Out of service

Never conflate booking, payment and readiness status.

**Reservation hold durations are pending client approval.**

Implement configurable hold policies for online, WhatsApp and manual-EFT holds, with optional variation by how close the arrival date is. Do not treat two hours or 24 hours as approved.

Until the client configures them, use this safe development-only workflow:
- Every hold must carry an explicit `hold_expires_at`, which staff choose when creating it.
- Public and WhatsApp self-service holds use a short, clearly labelled **development placeholder** that is configurable and flagged in the admin UI as "PENDING CLIENT DECISION".
- Indefinite holds are impossible.
- A scheduled job (every minute) expires holds and releases room nights.

If a payment arrives after its hold has expired:
- Re-check availability.
- If the room is still available, proceed through normal confirmation.
- If not, place the booking in the exceptions queue as "Late payment — rebook or refund" and alert reception.

Taxes and fees (VAT, tourism levy) are configurable and off by default. Do not hardcode tax rules.

---

# 6. PUBLIC ONLINE BOOKING

The booking flow:

1. Search dates and guests
2. Available accommodation
3. Room selection
4. Extras
5. Guest details
6. Price summary
7. Payment method
8. Reservation confirmation

Show:

- Nightly rate breakdown
- Taxes and fees where configured
- Extra services
- Deposit requirements
- Total payable
- Cancellation policy

Approved payment display order:

1. Instant EFT
2. Card
3. Ordinary EFT (banking details and a unique payment reference)

Collect only the guest details the booking needs, plus policy acknowledgements.

Marketing consent must be optional and separate from operational booking consent. Record consents with timestamps.

On completion:
- Create the reservation, room assignment, hold or confirmation, quotation or invoice, and folio records.
- Show a confirmation page.
- Send a confirmation email with the PDF attached, plus an SMS or WhatsApp link (using the log driver until providers are activated).

Never confirm a paid booking solely because a guest uploaded proof of EFT.

---

# 7. FINANCIAL DOCUMENTS AND PAYMENT CONTROL

Create separate entities for:

- Quotations
- Invoices
- Receipts
- Credit notes
- Payments (ledger)
- Refunds and refund approvals
- Guest folios and folio lines

Support:

- Sequential numbering per document type
- PDF generation with configurable branding
- Itemised charges
- Banking details
- Tax information when configured
- Unpaid and paid statuses
- Outstanding balances
- Converting a quotation to an invoice without retyping
- A receipt for every confirmed payment
- Financial exports (CSV and XLSX)
- Settlement reconciliation
- Audit history

Each document PDF carries a **reception QR code containing only an opaque, revocable token**, never personal data.

Issued invoices must never be silently edited, and are immutable at the model level. Corrections must use traceable adjustments or credit notes.

Create a `PaymentGateway` interface with:
- A `fake` development driver that simulates success, failure, and duplicate and out-of-order webhooks.
- Adapter placeholders for suitable South African providers, such as Ozow (Instant EFT), PayFast or Yoco. These stay **inactive** until a provider has been selected and technically verified.

Webhooks:
- Verify signatures.
- Enforce idempotency through a unique gateway event ID.
- Handle duplicate, missing and out-of-order events safely.

Manual EFT:
- Uploading proof of payment sets the status to `pending_verification` only.
- Payments stay pending until authorised staff confirm settlement, recording who, when and the bank reference.

Refunds require two distinct authorised participants (requester and approver) and record the reason, the gateway transaction, the reversal and the reconciliation status, with a complete audit trail.

---

# 8. WHATSAPP-ASSISTED BOOKINGS

Build a branded, colourful, date-specific availability price-list generator.

Reception selects dates (and optionally a guest phone number) and generates a secure, signed, expiring link (`/p/{token}`).

The customer can:

- View available accommodation
- Select rooms
- Add breakfast or dinner
- Add other services
- Enter name and surname
- Enter a phone number
- View an itemised total
- Submit a booking request
- Receive an unpaid invoice
- View payment instructions (Instant EFT, card, ordinary EFT)

Displayed availability must come from the central booking engine. Inventory must be revalidated before a reservation is accepted. The accepted request creates a hold under the configured policy, with a physical room assigned.

On verified payment:

- Update payment status
- Issue a receipt
- Confirm the reservation according to the booking rules
- Refresh shared inventory
- Notify reception
- Send the appropriate guest communication

WhatsApp messages carry secure document links. Email carries PDF attachments.

Every request appears in the reception dashboard with a real-time alert. Reception can send the link through a prefilled `wa.me` click-to-chat link, or through the WhatsApp provider once it is activated.

---

# 9. RECEPTION WORKSPACE

Build a comprehensive reception dashboard showing:

- Today's arrivals
- Today's departures
- Guests currently staying
- Available rooms
- Outstanding balances
- Pending EFT verification
- Expiring reservation holds
- Open guest requests
- WhatsApp price-list requests
- Maintenance alerts
- Booking conflicts and the exceptions queue

Implement an interactive room-by-date calendar:
- Rooms as rows and dates as columns.
- Colour-coded by booking and readiness status, with blocks shown.
- Click an empty cell to create a booking.
- Drag to reassign a room, which requires entering a reason.

Reception must be able to:

- Create and edit reservations
- Check guests in and out
- Extend bookings
- Reassign rooms with reasons
- Add charges
- Record payments
- Issue quotations, invoices and receipts
- Register key handovers
- Record key returns and lost keys
- Record towels and toiletries issued (deducting from stock)
- Register guest complaints
- Access returning guest profiles and their booking and document history
- Resend portal and document links by SMS, WhatsApp or email
- Capture guest-register details at check-in (configurable fields, restricted access, configurable retention; the legal requirements are pending verification)

Build a QR document scanner using a supported browser camera library (e.g. `html5-qrcode`).

Scanning an invoice or booking QR must retrieve the guest record inside an authenticated reception session. It shows a large, prominent page with:
- Guest name and contact details
- Booking details and assigned room
- Arrival and departure dates
- Payment status
- Breakfast and other purchased services
- Outstanding balance
- Previous bookings, where authorised

Every action listed above is available directly from that page.

Historical QR codes must stay usable for authorised guest lookup, subject to retention policies.

QR codes must use opaque, revocable references, never embedded personal data. Unauthenticated scans reveal nothing, and every lookup is audited.

---

# 10. OFFLINE RECEPTION CONTINGENCY

Implement a carefully controlled read-only offline facility as a PWA service worker. Authorised reception devices can access a limited, securely protected cache of:

- Today's arrivals
- Tomorrow's arrivals
- Room list
- Essential operational status

Security rules:
- Encrypt the cache with Web Crypto, using a key derived per session.
- Expire it after a short configurable period.
- Store the minimum personal data possible.
- Clear it on logout.

Display the last successful synchronisation time prominently.

If offline storage cannot be adequately secured on the target device, fall back to something safer, such as a printable or downloadable arrivals sheet generated while online, rather than storing sensitive data insecurely.

Do not allow confirmed bookings, payment verification or room allocation while offline.

---

# 11. GUEST ROOM QR PORTAL AND SMS VERIFICATION

Every physical room must have a unique, rotatable QR token (`/r/{room_qr_token}`) that identifies the room only.

Scanning the room QR opens a generic verification page. Never expose guest identity or reservation details before authentication.

The system must:

1. Identify the active checked-in stay.
2. Send a six-digit OTP to the registered booking phone number, or to a number authorised by the verified primary booker.
3. Store only a secure hash of the OTP. Staff can never see OTP values.
4. Expire the OTP after five minutes.
5. Limit verification attempts.
6. Rate-limit SMS requests per room, IP and phone number.
7. Detect possible SMS pumping, with a daily cap and an owner alert.
8. Respond identically whatever the outcome (anti-enumeration).
9. Create a secure session bound to the stay and room after successful verification.

The verified primary booker may authorise additional guest phone numbers and revoke them. Every authorisation and revocation must be logged.

Reception needs an audited identity-verification recovery procedure for guests who can't receive the SMS.

SMS links (`/s/{signed-token}`, time-limited) must also be supported and must lead to the **same** guest services backend.

The guest portal must provide:

- Welcome information
- Wi-Fi information
- House rules
- Emergency contacts
- Facility operating hours
- Authorised booking information
- Food menus
- Meal ordering (allergens, cut-off times, charge-to-room, kitchen tickets)
- Towels and toiletries
- Reception messaging, with audible and visual alerts at reception and configurable escalation
- Spa and day-pass reservations
- VIP area booking
- Itemised account
- Checkout request
- Reviews, ratings and photos (moderated, EXIF-stripped, published only with consent)

Revoke stay-bound access on checkout, room reassignment or security revocation. Guests can never see other guests' data or previous occupants' messages.

---

# 12. AUTOMATED MESSAGING

Create interfaces for:

- SMS provider (stub adapters for candidate South African SMS gateways)
- WhatsApp provider (stub adapter for the Meta WhatsApp Cloud API with approved templates)
- Email provider (Laravel Mail)

Use log or fake drivers in local development.

Implement configurable message templates, with each send logged:

- Channel
- Language
- Version
- Variables
- Operational or marketing classification
- Delivery status
- Retry history
- Cost estimate
- Idempotency key

Delivery webhooks update the logged status. Retries use backoff and have a maximum number of attempts.

Automations must support:

- Booking enquiry acknowledgement
- Availability price-list follow-up
- Unpaid invoice reminder
- Payment confirmation
- Pre-arrival information
- Arrival-day guest portal link
- Checkout reminder (departure-day review-bill link)
- Thank-you and safe-travels message
- Optional post-stay feedback
- Employee task notifications
- Inspection notifications
- Management escalation

Respect guest consent, communication preferences and opt-outs. Never send marketing without consent.

Support monthly messaging budgets: alert the owner as spending nears the configured limit (default 80%), and stop non-critical sends at 100%.

Do not send real SMS or WhatsApp messages until providers have been approved and activated.

---

# 13. GUEST CHECKOUT AND ROOM TURNOVER

Guests may request checkout through the guest portal (room QR or SMS link) or at reception.

Before completing checkout, check for:

- Unpaid room charges
- Additional services
- Unverified EFT payments
- Disputes
- Outstanding balances

If anything is unresolved, route the checkout to reception as a task and never complete it silently.

Allow controlled manager overrides, with reasons and audit records, for legitimate credit or corporate arrangements. The exact override authority is a pending decision.

On completed checkout:

1. Record departure.
2. Close the stay.
3. Revoke guest portal access.
4. Generate final financial documents where appropriate.
5. Send the thank-you and safe-travels message once.
6. Create the housekeeping turnover task.
7. Mark the room dirty.
8. Notify the assigned employee.

Use idempotent jobs (unique keys per stay and event) so retries never duplicate messages or tasks.

---

# 14. EMPLOYEE MANAGEMENT AND STAFF PORTAL

Create a separate employee dashboard (`/staff`).

Employees must be able to view only their own:

- Assigned shifts
- Tasks
- Notifications
- Attendance history (weekly and monthly)
- Approved leave
- Task completion history

Employees complete their own profiles (name, contacts, selfie or photo, emergency contact). An administrator approves or corrects each profile.

Managers must be able to:

- Create employee profiles and departments
- Allocate shifts
- Manage weekly rosters
- Approve leave
- Manage public holidays (an editable list, with demo data labelled)
- Reassign tasks
- View attendance reports

Staff must not be marked absent on days they are not scheduled to work.

Do not send non-urgent work notifications to off-duty staff unless specifically authorised.

---

# 15. EXISTING HIKVISION ATTENDANCE MACHINE

**MANDATORY EXISTING DEVICE: Hikvision DS-K1T342MFWX-E1.** This device must remain in service.

It supports:

- Face recognition
- Fingerprint authentication
- M1 card
- PIN
- Ethernet and Wi-Fi
- ISAPI and ISUP capabilities

Create an `AttendanceSource` interface with:

- A Hikvision adapter, with configurable host, credentials and auth method. It stays **disabled until verified** on the physical device.
- A CSV import fallback.
- An authorised manual entry fallback.

Investigate the actual device firmware and its supported attendance-event endpoints, and document the findings in `docs/HIKVISION_POC.md`. Do not assume any particular ISAPI endpoint (e.g. `/ISAPI/AccessControl/AcsEvent`) or authentication method works until it has been tested on the physical device.

Where the device supports it, implement:
- Paginated event retrieval
- Incremental synchronisation
- Time-zone handling
- Duplicate-event protection, using a composite dedup key (device + employee device ID + timestamp + event type + source event ID; never sequence numbers alone)
- A device-offline alert

Store raw attendance punches separately from derived daily attendance.

Never store biometric templates in the Laravel database. Store only events and the employee-to-device-ID mapping.

Offer PIN or card as a genuine, non-punitive alternative to biometrics, and publish an employee privacy notice.

Calculate:

- Worked
- Late
- Absent
- Incomplete
- Off duty
- Pending review

Compare attendance against assigned shifts, configurable clock-in and clock-out windows, approved leave and scheduled days off. Authorised manual corrections require a reason and are audited.

Provide monthly payroll-ready exports in CSV and XLSX.

Full payroll and general ledger processing are outside the approved scope.

Record the acceptance test from Specification Section 26 in `docs/HIKVISION_POC.md`.

---

# 16. HOUSEKEEPING, INSPECTIONS AND MAINTENANCE

Create task management for:

- Daily cleaning
- Light room refresh (sweep, mop, scrub shower, make bed, replenish sugar and coffee, verify towels)
- Checkout cleaning
- Monday deep cleaning
- Gardening (plants and flowers, cutting grass)
- Maintenance (pressure-washing paving, polishing floors, painting)
- Pool cleaning and fish-pond scrubbing
- VIP-area cleaning
- Braai-area cleaning
- General repairs

Tasks must include:

- Assigned employee
- Location (room, area or asset)
- Checklist
- Priority
- Due date
- Status
- Completion evidence
- Optional photographs
- Estimated effort (configurable by room type and task type)

Reception allocates housekeeping tasks. The owner or a supervisor allocates gardening and maintenance tasks.

Automatically generate Monday deep-clean tasks for every room.

Deep cleaning must include:

- Under beds
- Floors
- Showers
- Beds
- Aluminium windows
- Aluminium doors
- Mirrors
- Microwave
- Kettle
- Drinking-water replacement
- Toiletries
- Towels
- Coffee and sugar

The turnover flow runs `dirty → cleaning → awaiting_inspection → ready`.

Inspectors score individual criteria on a configurable scale and can attach before-and-after photos. Inspection results:
- Failed items create rework tasks with a due time.
- The responsible worker is notified and a re-inspection is scheduled.
- A pass makes the room ready.
- Managers can track unresolved failures.

Completing a room's cleaning does not automatically mean it has passed inspection.

Support preventive maintenance schedules, asset histories, warranties and supplier costs. Keep a stock ledger for towels, toiletries, coffee, sugar and cleaning supplies, recording stock in, out and adjustments, with low-stock alerts.

---

# 17. LEISURE SERVICES AND FOOD

Create bookable services for:

- Swimming pool day passes
- Braai facilities
- VIP Area 1
- VIP Area 2
- Spa
- Massage treatments
- Breakfast
- Dinner
- Other configurable offerings

Support:

- Prices (demo values only, clearly labelled)
- Capacity limits
- Opening hours
- Time slots
- Service duration
- Booking restrictions
- Cancellation policies
- Indemnity acknowledgements (pool, braai)
- Payment records

For spa bookings, check that both the therapist and the treatment room are available.

For meals, support menus, allergens, cut-off times and kitchen tickets, shown on a simple kitchen display page.

Services can be booked publicly, at reception or through the guest portal. In-house guests must be able to charge eligible services to their guest folio.

Vouchers, packages and event or function hire may be implemented behind feature flags, as recommended features rather than approved ones.

---

# 18. OWNER DASHBOARD AND DAILY REPORT

Create a comprehensive owner dashboard showing:

- Occupancy percentage
- Average daily rate (ADR)
- Revenue per available room (RevPAR)
- Revenue and payments
- Outstanding balances
- Booking sources
- Arrivals and departures
- Staff attendance
- Housekeeping performance
- Inspection scores
- Maintenance issues
- Guest complaints
- Unresolved requests
- Integration exceptions

Document the financial metric formulas in both code docblocks and `docs/ARCHITECTURE.md`.

**Every day at 17:00 Africa/Johannesburg:**

1. Generate and store a daily operational report snapshot.
2. Email the owner a secure, authenticated link.
3. Send a web push notification (VAPID).
4. Log delivery status.
5. Retry failed notifications safely.

Do not include unnecessary sensitive employee or guest information in email bodies, and never include payroll or biometric details.

Provide a historical report archive restricted to authorised users, and an optional "day close" that freezes reconciled totals.

Create escalation rules for urgent or unanswered guest requests. They run independently of the daily report, with configurable timers, service hours, backup contacts and an emergency override.

---

# 19. EXTERNAL BOOKING CHANNELS

Approved platforms:

- Booking.com
- LekkeSlaap

Airbnb is **not** an approved initial integration.

The architecture is not yet approved. In `docs/INTEGRATIONS.md`, compare:

1. Custom Laravel plus a commercially supported channel manager.
2. A hybrid PMS plus custom Laravel modules.

List NightsBridge, Rentals United and NextPax as candidates without selecting one. Note the evidence still required for each:
- Sandbox access
- Webhooks
- Rate limits
- Data ownership
- Exit and export terms
- Outage behaviour

Create a `ChannelManager` interface supporting:

- Reservation imports
- Availability updates
- Rate updates
- Synchronisation status
- Conflict reporting
- Retry handling

Provide a null development driver.

Investigate iCal import and export as a limited fallback where supported, but do not present it as equivalent to a full channel manager.

External bookings must receive physical room assignments through `AvailabilityService` as soon as they are ingested.

If no valid room is available, put the reservation in the exceptions queue and alert reception.

Do not silently overbook.

Provide a sync-status page showing:
- Last sync per channel
- Stale-sync warnings
- A retry button
- Integration event logs
- Scheduled reconciliation

Use the `paid_to_channel` payment status for bookings the channel has collected payment for.

Do not claim Booking.com or LekkeSlaap is live until a supported provider connection has been verified.

---

# 20. DATA PROTECTION AND COMPLIANCE

Create configurable retention settings for:

- Guest registration
- Bookings
- Messages
- Attendance
- Uploaded documents
- Financial records

Support legal-hold flags, consent records, access auditing and authorised data exports (including per-guest POPIA access requests).

Provide an anonymisation framework, but keep it disabled. Do not enable irreversible deletion until retention policies are approved.

Maintain `docs/COMPLIANCE_TODO.md` covering:

- South African guest-register requirements
- POPIA
- PAIA
- Tax invoice rules
- VAT applicability
- Biometric information
- Labour and attendance records
- Tourism grading
- Payment security

Do not hardcode unverified legal requirements.

Configure `spatie/laravel-backup` for the database and private files, sending backups to a configurable disk. Document the restore procedure and perform a test restore in Milestone 13.

---

# 21. DEVELOPMENT DEMONSTRATION DATA

Create development-only factories and seeders, guarded so they run only in the `local` environment.

Use clearly fictional information for:

- Staff accounts
- Room categories (e.g. "Demo Family Suite")
- Named rooms (e.g. "Demo Room 01")
- Guests
- Reservations
- Payments
- Invoices
- Services
- Spa treatments
- News articles
- Housekeeping tasks
- Attendance events
- Inspection records

Provide enough demonstration data to test:

- Occupancy
- Availability
- Booking conflicts
- Room changes
- Payment states
- Guest portal access (at least two in-house stays with working sample room QR links)
- Employee schedules
- Inspection failures
- Owner reports

Never present development seed data as real Baseline Lodge information.

Generate a random password for each demo account at seed time and write them to a git-ignored `storage/app/dev-credentials.txt`. Display them once in the console. Seeders must refuse to run in production.

Never deploy predictable demo passwords or demonstration accounts to production.

---

# 22. AUTOMATED TESTING

Write tests covering at minimum:

1. Concurrent bookings for the final available room
2. Multi-night availability
3. Room maintenance blocks
4. Seasonal pricing
5. Booking status transitions
6. Payment status transitions
7. Reservation hold expiry and inventory release
8. Duplicate and out-of-order payment webhooks
9. Manual EFT verification (proof of payment never marks a booking paid)
10. Dual refund approval
11. Immutable issued invoices and the credit-note path
12. QR-code privacy (the room QR shows nothing; unauthenticated document QRs reveal nothing)
13. OTP expiry, attempt limits, rate limits and anti-enumeration
14. Guest portal session revocation on checkout and room transfer
15. Checkout balance checks and audited overrides
16. Duplicate checkout event handling (one farewell message, one turnover task)
17. Housekeeping turnover
18. Inspection failure and rework
19. Attendance deduplication
20. Off-duty employees not being marked absent
21. Employee access restrictions (employees see only their own data)
22. Daily report generation at 17:00 Africa/Johannesburg, with no sensitive data in the email
23. Role-based permissions for every panel, resource and action
24. External booking conflict handling (quarantine, no overbooking)
25. Quotation-to-invoice conversion
26. Late payment arriving after hold expiry

Run the relevant tests after each milestone, plus the full suite at Milestones 2, 7 and 13.

Run Laravel Pint and `npm run build`.

Use the PostgreSQL test database for all database-dependent integration and concurrency tests, so they exercise real production database behaviour.

Do not claim tests pass unless they have actually run. Record the commands and results in `docs/TESTING.md`.

---

# 23. ARCHITECTURE NOTES

- Keep domain modules under `app/Domain/{Booking,Finance,Guests,Portal,Housekeeping,Workforce,Services,Messaging,Channels,Reporting,Cms}` (or an equivalent clear structure).
- Pending decisions live in a typed settings store, editable in admin with "PENDING CLIENT DECISION" badges where applicable.
- Feature flags cover recommended (not approved) features.
- Every job that sends messages or creates tasks is idempotent.
- Run the scheduler with `php artisan schedule:work` locally. Document the Windows Task Scheduler or production cron equivalent in the README.

---

# 24. IMPLEMENTATION MILESTONES

Execute in order:

## Milestone 1 — Foundation
- Inspect the existing application.
- Verify PostgreSQL.
- Install compatible dependencies.
- Configure authentication and 2FA.
- Implement roles and permissions.
- Configure the administration and staff panels.
- Establish audit logging.
- Create system settings.
- Build base layouts and the design system.
- Create the documentation set.

## Milestone 2 — Accommodation and Booking Engine
- Accommodation categories, physical rooms, rates and seasonal pricing.
- The `AvailabilityService`.
- Room allocation, room-night constraints and the exclusion guard.
- Booking, payment and readiness state machines.
- Holds and the expiry job.
- Concurrency tests.
- **Vertical slice:** reception creates a booking, a room is auto-assigned, and it appears on the calendar.

## Milestone 3 — Public Website
- Homepage and room pages.
- CMS, news and events.
- Services pages.
- SEO and redirects.
- Responsive design.

## Milestone 4 — Reservations and Finance
- Online booking flow and guest profiles.
- Quotations, invoices, receipts, credit notes, folios and PDFs with QR codes.
- Payment abstractions with the fake gateway.
- Manual EFT verification.
- Refund workflow.
- Exports.

## Milestone 5 — Reception
- Reception dashboard and booking calendar.
- Check-in and checkout.
- Guest register and key log.
- Document QR scanner.
- Offline read-only contingency.

## Milestone 6 — Messaging and WhatsApp Bookings
- Live price lists.
- SMS, WhatsApp and email interfaces and templates.
- Message logs and the budget cap.
- Automation rules.

## Milestone 7 — Guest Portal
- Room QR codes and SMS links.
- SMS OTP verification.
- Additional authorised guest phones and the recovery procedure.
- Guest service requests and inbox.
- Checkout automation.
- Reviews and feedback.

## Milestone 8 — Housekeeping and Maintenance
- Cleaning assignments and Monday deep cleaning.
- Inspections and rework tasks.
- Maintenance and preventive schedules.
- Stock control.

## Milestone 9 — Employees and Attendance
- Employee profiles, shift scheduling, leave and the staff portal.
- Attendance events and derived attendance.
- Hikvision integration feasibility.
- CSV and manual fallbacks.
- Attendance reports and exports.

## Milestone 10 — Leisure and Food
- Day passes, pool and braai bookings.
- VIP areas.
- Spa bookings (therapist and room).
- Menus and kitchen tickets.
- Charge-to-room.

## Milestone 11 — Management Reporting
- Owner dashboard and occupancy analytics.
- Financial reporting and staff performance reports.
- The 17:00 daily email and push notification, and the report archive.
- Escalation rules.

## Milestone 12 — External Channels
- Channel manager abstraction.
- Booking.com and LekkeSlaap integration readiness.
- iCal fallback.
- Synchronisation monitoring, exception handling and reconciliation.

## Milestone 13 — Final Quality and Documentation
- Compliance configuration.
- Security review.
- Backup and restore testing.
- Performance and accessibility testing.
- Full automated test suite.
- Production readiness checklist.
- Updated documentation.

Do not skip milestone testing.

---

# 25. REQUIRED DELIVERABLES

Deliver a working Laravel application that runs from VS Code (`Dev: all` task) at `http://127.0.0.1:8000`, or at `http://baseline-lodge.test` if Herd serves it.

Maintain:

- `README.md`
- `docs/ARCHITECTURE.md`
- `docs/REQUIREMENTS_TRACEABILITY.md`
- `docs/PENDING_DECISIONS.md`
- `docs/INTEGRATIONS.md`
- `docs/COMPLIANCE_TODO.md`
- `docs/IMPLEMENTATION_PROGRESS.md`
- `docs/TESTING.md`
- `docs/HIKVISION_POC.md`
- `docs/BACKUP_RESTORE.md`

The README must explain:

- Local installation on Windows with VS Code: prerequisites, required PHP extensions, recommended extensions and the VS Code tasks (with optional Herd)
- PostgreSQL configuration
- Migrations
- Development seeders and where the credentials are written
- Queue workers
- Scheduler
- Admin access (`/admin`)
- Staff access (`/staff`)
- Guest portal (`/stay`, sample `/r/{token}`)
- Integration driver configuration
- Backup and restoration
- Test execution

At the end of each milestone, report:

1. What was implemented.
2. Files created or modified.
3. Database migrations.
4. Available routes.
5. Tests executed and results (pass and fail counts).
6. Known limitations.
7. Unverified integrations.
8. Pending decisions.
9. Next milestone.

---

# 26. BEGIN DEVELOPMENT NOW

Start with Milestone 1.

Inspect the VS Code workspace and the installed environment (Section 0A). Set up the `.vscode` files, the agent rules file, and `docs/MASTER_PROMPT.md` (a copy of this prompt).

Verify that PostgreSQL is running and that the `baseline_lodge` database is accessible.

If the database does not exist, create it only when authorised, using the configured local PostgreSQL credentials. Do the same for `baseline_lodge_testing`.

Inspect existing migrations and preserve existing data.

Install only compatible dependencies.

Implement:

- Authentication and 2FA
- Roles and permissions
- Filament administration
- Staff panel foundation
- Audit logging
- Settings management
- Base application layout
- Initial project documentation

Run migrations, tests and code formatting checks, then provide a concise implementation report.

Continue through the milestones where requirements are approved and technical dependencies are available. Do not invent missing business decisions, and do not activate real third-party services without approval.

**The objective is a complete, secure, maintainable, commercially deployable Baseline Lodge hospitality platform built with Laravel and PostgreSQL, faithfully implementing Specification Version 5.3.**
