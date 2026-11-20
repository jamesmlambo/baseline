<!--
Source: the other team's original Baseline Lodge prompt, pasted into the claude.ai chat
"Laravel Herd guesthouse website prompt" as "Pasted text.txt" (29.3 kB, 1,195 lines).
Copied verbatim on 2026-11-20 for reference only. docs/MASTER_PROMPT.md is the working
version built from it. Note: the "approved payment display order" in section 6 comes
from this prompt, not from SPEC v5.3, which only approves Instant EFT first.
-->

# BASELINE LODGE — MASTER DEVELOPMENT PROMPT
## Consolidated Specification v5.3 | Laravel Herd Studio | PostgreSQL

You are the principal software architect, senior Laravel developer, PostgreSQL engineer, UI/UX designer, security engineer, integration specialist and test engineer responsible for developing the **Baseline Lodge Guesthouse Website and Management Platform**.

Build a complete, professional, mobile-first hospitality website and integrated guesthouse management application in THIS Laravel project.

The attached "Guesthouse Management Platform — Consolidated Requirements Version 5.3" is the authoritative functional specification. This prompt supplements that specification with implementation instructions.

The official guesthouse name is **Baseline Lodge**. Never use Nyala Guesthouse in branding, page titles, generated content, emails, application configuration or new documentation.

Your assignment is to implement the approved requirements systematically, not merely produce a plan.

Work through the milestones in order. After each milestone, run relevant migrations, tests and quality checks. Fix failures before proceeding.

Do not fabricate working integrations, business policies, guesthouse information or successful test results.

---

# 0. MANDATORY TECHNICAL STACK

- Application: Baseline Lodge
- Project folder: `baseline-lodge`
- Laravel: Version 13, subject to installed-environment compatibility
- PHP: 8.4 or compatible supported version
- Primary database: PostgreSQL
- Database name: `baseline_lodge`
- Local environment: Laravel Herd on Windows
- Public frontend: Blade, Tailwind CSS, Alpine.js and Livewire
- Administration: Filament, latest stable release compatible with installed Laravel
- Staff self-service: Separate Filament panel at `/staff`, if compatible
- Guest portal: Mobile-first Livewire interface at `/stay`
- Authentication: Laravel-compatible secure authentication with TOTP 2FA
- Authorisation: Spatie Laravel Permission or equivalent
- Audit logging: Spatie Activitylog or equivalent
- Media: Spatie Media Library or equivalent secure implementation
- PDF documents: Supported Laravel PDF generation package
- QR generation: Compatible QR-code package
- Testing: Pest or PHPUnit
- Code formatting: Laravel Pint
- Queue driver: Database initially
- Local email driver: Log
- Scheduler: Laravel Scheduler
- Timezone: Africa/Johannesburg
- Locale: en_ZA
- Currency: ZAR
- Financial storage: Integer cents
- Source control: Git

**PostgreSQL is mandatory for the primary application database. Do not substitute MySQL or SQLite.**

Before installing any package, verify compatibility with the installed Laravel, PHP, Livewire and Filament versions. Do not force incompatible versions or downgrade an existing project without approval.

Use PHP enums for domain statuses, Actions/Services for business logic, Form Requests for validation, Policies for permissions and interfaces for external integrations.

Use database transactions, foreign keys, indexes, unique constraints and appropriate concurrency controls.

Store timestamps consistently and present business times in Africa/Johannesburg.

---

# 1. PROJECT AUDIT AND DEVELOPMENT RULES

Before making changes:

1. Inspect the existing Laravel project.
2. Check PHP, Composer, Node.js, Laravel, PostgreSQL and installed packages.
3. Inspect existing routes, models, migrations, controllers, tests and configuration.
4. Verify PostgreSQL connectivity.
5. Identify existing data and functionality that must be preserved.
6. Read the complete Version 5.3 specification.
7. Establish a Git baseline.
8. Run existing tests.
9. Prepare the database architecture.
10. Create a requirements traceability register.

Create the following documents:

- `docs/REQUIREMENTS_TRACEABILITY.md`
- `docs/ARCHITECTURE.md`
- `docs/PENDING_DECISIONS.md`
- `docs/INTEGRATIONS.md`
- `docs/COMPLIANCE_TODO.md`
- `docs/IMPLEMENTATION_PROGRESS.md`
- `docs/TESTING.md`

Categorise requirements as:

- Approved
- Pending client decision
- Recommended enhancement
- Requires external verification
- Implemented
- Tested
- Blocked

Do not silently convert pending recommendations into approved business policies.

Do not execute destructive database operations, delete existing data or overwrite working features without explicit approval.

Proceed autonomously with approved implementation tasks. Stop and request approval only for consequential unresolved decisions, destructive operations, real financial transactions, or external service activation.

---

# 2. AUTHENTICATION, ROLES AND SECURITY

Implement these roles:

- Administrator
- Owner
- Manager
- Receptionist
- Housekeeping Worker
- Maintenance Worker
- Inspector
- Content Editor

Users may hold multiple roles where authorised.

Guests do not require normal staff accounts. They access a separate secure guest portal.

Implement:

- Role-based access control
- Server-side Laravel Policies
- Filament panel access restrictions
- TOTP two-factor authentication for privileged staff
- Login rate limiting
- Password reset
- Secure session management
- Audited role changes
- Permission checks on every sensitive action

Audit:

- Booking changes
- Room assignments
- Payments
- Refunds
- Financial document changes
- Attendance corrections
- Guest profile changes
- Role changes
- System settings

Encrypt appropriate sensitive information. Where encrypted fields require searching, use carefully designed normalised keyed lookup hashes rather than searching ciphertext.

Secure uploaded files with MIME validation, size limits, access controls and metadata removal where appropriate.

Provide an antivirus scanning integration interface, but do not claim scanning is active until a real scanner is connected.

Create an official communication policy explaining that Baseline Lodge will not request card credentials through WhatsApp or SMS.

---

# 3. PUBLIC BASELINE LODGE WEBSITE

Develop a premium, modern and colourful-but-tasteful guesthouse website.

The design must be responsive, accessible and fast, targeting WCAG 2.1 AA and strong Lighthouse performance.

Pages:

1. Home
2. About Us
3. Our Rooms
4. Room Details
5. Features and Amenities
6. Services
7. Swimming Pool and Day Passes
8. Braai Facilities
9. VIP Areas
10. Spa and Massage Services
11. News and Events
12. FAQs
13. Contact Us
14. Check Availability
15. Book Accommodation
16. Guest Portal
17. House Rules
18. Booking and Cancellation Policies
19. Privacy Policy
20. Official Communication Policy

Homepage requirements:

- Hero image or slider
- Availability search
- Featured accommodation
- Room prices from the configured rate engine
- Facilities and services
- Spa and leisure highlights
- Recent three news articles
- Guest reviews
- Contact information
- WhatsApp contact
- Booking call-to-action

Create an administration-managed CMS with:

- Pages
- News articles
- Events
- FAQs
- Galleries
- Amenities
- Site settings
- SEO metadata
- Open Graph metadata
- Sitemap
- Redirect management

Support structured data such as LodgingBusiness and HotelRoom where applicable.

Tourism grading must only be displayed when verified evidence has been provided.

Do not invent real guesthouse photographs, room inventory, prices, contact information or grading.

Use clearly identified demonstration content until real information is provided.

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

Accommodation types describe categories and pricing.

Physical rooms represent actual named rooms.

Every accepted booking must receive a specific physical room assignment immediately.

Room assignment must be recorded separately and audited.

Use a central `AvailabilityService` shared by:

- Public website
- Reception dashboard
- WhatsApp price-list system
- External channel imports
- Booking amendments

Implement an inventory allocation mechanism using PostgreSQL transactions and constraints.

Consider a `room_nights` occupancy ledger with a unique constraint on `(room_id, night_date)` for active inventory claims. Design its lifecycle carefully so cancellations, maintenance blocks, expired holds and historical records do not conflict incorrectly.

Revalidate availability before confirming every booking.

Test concurrent reservations for the last available room. Exactly one conflicting reservation may succeed.

Do not rely only on frontend availability checks.

---

# 5. BOOKING AND RESERVATION ENGINE

Support:

- Online bookings
- Reception bookings
- WhatsApp-assisted bookings
- Returning guests
- Multi-room reservations
- Group bookings
- Room extensions
- Early checkout
- Room reassignment
- Cancellation
- No-shows
- Maintenance blocks
- Special requests
- Extras and discounts

Use independent state machines.

Booking states may include:

- Quote
- Hold
- Pending
- Confirmed
- Checked in
- Checked out
- Cancelled
- No-show
- Expired

Payment states may include:

- Unpaid
- Part-paid
- Pending verification
- Paid to external channel
- Verified paid
- Part-refunded
- Refunded

Room readiness states may include:

- Dirty
- Cleaning
- Awaiting inspection
- Ready
- Occupied
- Out of service

Reject invalid status transitions.

Keep status history and audit records.

**Reservation hold durations are pending client approval.**

Implement configurable settings for online and manual-EFT holds, but do not treat two hours or 24 hours as approved policies.

Until configured, use a safe development-only workflow that does not create uncontrolled indefinite holds.

---

# 6. PUBLIC ONLINE BOOKING

Create the following flow:

Search dates and guests → Available accommodation → Room selection → Extras → Guest details → Price summary → Payment method → Reservation confirmation.

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
3. Ordinary EFT

Collect necessary guest details and policy acknowledgements.

Marketing consent must be optional and separate from operational booking consent.

Create the appropriate reservation and financial records.

Never confirm a paid booking solely because a guest uploaded proof of EFT.

---

# 7. FINANCIAL DOCUMENTS AND PAYMENT CONTROL

Create separate entities for:

- Quotations
- Invoices
- Receipts
- Credit notes
- Payments
- Refunds
- Guest folios

Support:

- Sequential numbering
- PDF generation
- Itemised charges
- Banking details
- Tax information when configured
- Unpaid and paid statuses
- Outstanding balances
- Financial exports
- Reconciliation
- Audit history

Issued invoices must not be silently edited. Corrections must use traceable adjustments or credit notes.

Create a `PaymentGateway` interface.

Provide a fake development driver and integration adapters or placeholders for suitable South African providers, such as Ozow, PayFast or Yoco, subject to selection and technical verification.

Verify webhook signatures and handle duplicate or out-of-order events safely.

Manual EFT payments must remain pending verification until authorised staff confirm settlement.

Refunds require two distinct authorised participants and a complete audit trail.

---

# 8. WHATSAPP-ASSISTED BOOKINGS

Build a branded, date-specific availability price-list generator.

Reception selects dates and generates a secure expiring link.

The customer can:

- View available accommodation
- Select rooms
- Add breakfast or dinner
- Add other services
- Enter name and surname
- Enter phone number
- View an itemised total
- Submit a booking request
- Receive an unpaid invoice
- View payment instructions

The availability displayed must be drawn from the central booking engine.

The system must revalidate inventory before accepting a reservation.

On verified payment:

- Update payment status
- Issue receipt
- Confirm the reservation according to the booking rules
- Notify reception
- Send the appropriate guest communication

WhatsApp sends secure document links.

Email may send PDF attachments.

---

# 9. RECEPTION WORKSPACE

Build a comprehensive reception dashboard with:

- Today's arrivals
- Today's departures
- Guests currently staying
- Available rooms
- Outstanding balances
- Pending EFT verification
- Expiring reservation holds
- Open guest requests
- Maintenance alerts
- Booking conflicts

Implement an interactive room-by-date calendar.

Reception must be able to:

- Create and edit reservations
- Check guests in and out
- Extend bookings
- Reassign rooms with reasons
- Add charges
- Record payments
- Issue invoices and receipts
- Register key handovers
- Record key returns
- Record towels and toiletries issued
- Register guest complaints
- Access returning guest profiles

Build a QR document scanner using a supported browser camera library.

Scanning an invoice or booking QR must retrieve the guest record inside an authenticated reception session.

Historical QR codes must remain useful for authorised guest lookup, subject to retention policies.

Use opaque, revocable references rather than embedding personal data inside QR codes.

---

# 10. OFFLINE RECEPTION CONTINGENCY

Implement a carefully controlled read-only offline facility.

Allow authorised reception devices to access a limited, securely protected cache of:

- Today's arrivals
- Tomorrow's arrivals
- Room list
- Essential operational status

Display the last successful synchronisation time.

Use short expiration periods and minimise sensitive cached information.

If offline storage cannot be adequately secured on the target device, use a safer restricted fallback instead of storing sensitive data insecurely.

Do not permit confirmed bookings, payment verification or room allocation while offline.

---

# 11. GUEST ROOM QR PORTAL AND SMS VERIFICATION

Every physical room must have a unique rotatable QR token.

Scanning the room QR opens a generic verification page.

Never expose guest identity or reservation details before authentication.

The system must:

1. Identify the active checked-in stay.
2. Send a six-digit OTP to the registered booking phone number.
3. Store only a secure hash of the OTP.
4. Expire the OTP after five minutes.
5. Limit verification attempts.
6. Rate-limit SMS requests.
7. Detect possible SMS pumping.
8. Create a secure session after successful verification.

The verified primary booker may authorise additional guest phone numbers.

Authorisation and revocation must be logged.

Reception must have an audited identity-verification recovery procedure.

SMS links must also be supported.

The guest portal must provide:

- Welcome information
- Wi-Fi information
- House rules
- Emergency contacts
- Facility operating hours
- Food menus
- Meal ordering
- Towels and toiletries
- Reception messaging
- Spa and day-pass reservations
- VIP area booking
- Itemised account
- Checkout request
- Reviews and feedback

Revoke stay-bound access on checkout, room reassignment or security revocation.

---

# 12. AUTOMATED MESSAGING

Create interfaces for:

- SMS provider
- WhatsApp provider
- Email provider

Use log/fake drivers in local development.

Implement configurable message templates with:

- Channel
- Language
- Version
- Variables
- Operational or marketing classification
- Delivery status
- Retry history
- Cost estimate
- Idempotency key

Automations must support:

- Booking enquiry acknowledgement
- Availability price-list follow-up
- Unpaid invoice reminder
- Payment confirmation
- Pre-arrival information
- Arrival-day guest portal link
- Checkout reminder
- Thank-you and safe-travels message
- Optional post-stay feedback
- Employee task notifications
- Management escalation

Respect guest consent and communication preferences.

Support monthly messaging budgets and owner alerts when spending approaches the configured limit.

Do not send real SMS or WhatsApp messages until providers are approved and activated.

---

# 13. GUEST CHECKOUT AND ROOM TURNOVER

Guests may request checkout through the guest portal or reception.

Before completing checkout, check:

- Unpaid room charges
- Additional services
- Unverified EFT payments
- Disputes
- Outstanding balances

If unresolved, route checkout to reception.

Allow controlled manager overrides with reasons and audit records.

On completed checkout:

1. Record departure.
2. Close the stay.
3. Revoke guest portal access.
4. Generate final financial documents where appropriate.
5. Send the thank-you and safe-travels message once.
6. Create the housekeeping turnover task.
7. Mark the room dirty.
8. Notify the assigned employee.

Use idempotent jobs to prevent duplicate messages or tasks.

---

# 14. EMPLOYEE MANAGEMENT AND STAFF PORTAL

Create a separate employee dashboard.

Employees must be able to view:

- Their assigned shifts
- Their tasks
- Their notifications
- Their attendance history
- Their approved leave
- Their task completion history

Managers must be able to:

- Create employee profiles
- Allocate shifts
- Manage weekly rosters
- Approve leave
- Reassign tasks
- View attendance reports

Staff must not be marked absent when they are not scheduled to work.

Avoid sending non-urgent work notifications to off-duty staff unless specifically authorised.

---

# 15. EXISTING HIKVISION ATTENDANCE MACHINE

**MANDATORY EXISTING DEVICE: Hikvision DS-K1T342MFWX-E1.**

This device must remain in service.

It supports:

- Face recognition
- Fingerprint authentication
- M1 card
- PIN
- Ethernet and Wi-Fi
- ISAPI/ISUP capabilities

Create an `AttendanceSource` interface with:

- Hikvision adapter
- CSV import fallback
- Authorised manual entry fallback

Investigate actual device firmware and supported attendance-event endpoints.

Do not assume a specific ISAPI endpoint or authentication method works until tested on the physical device.

Where supported, implement paginated event retrieval, incremental synchronisation, time-zone handling and duplicate-event protection.

Store raw attendance punches separately from derived daily attendance.

Never store biometric templates in the Laravel database.

Calculate:

- Worked
- Late
- Absent
- Incomplete
- Off duty
- Pending review

Compare attendance against assigned shifts, approved leave and scheduled days off.

Provide monthly payroll-ready exports.

Full payroll and general ledger processing are outside the approved scope.

---

# 16. HOUSEKEEPING, INSPECTIONS AND MAINTENANCE

Create task management for:

- Daily cleaning
- Light room refresh
- Checkout cleaning
- Monday deep cleaning
- Gardening
- Maintenance
- Pool cleaning
- VIP-area cleaning
- Braai-area cleaning
- General repairs

Tasks must include:

- Assigned employee
- Location
- Checklist
- Priority
- Due date
- Status
- Completion evidence
- Optional photographs

Automatically generate Monday deep-clean tasks.

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

Inspectors must score individual criteria using a configurable scale.

Failed items create rework tasks.

Notify the responsible worker and schedule re-inspection.

Room cleaning completion does not automatically mean inspection approval.

Support preventive maintenance schedules, asset histories and stock tracking.

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

- Prices
- Capacity limits
- Opening hours
- Time slots
- Service duration
- Booking restrictions
- Cancellation policies
- Payment records

For spa bookings, check therapist and treatment-room availability.

For meals, support menus, allergens, cut-off times and kitchen tickets.

In-house guests must be able to charge eligible services to their guest folio.

Vouchers and event packages may be implemented behind feature flags.

---

# 18. OWNER DASHBOARD AND DAILY REPORT

Create a comprehensive owner dashboard showing:

- Occupancy percentage
- Average daily rate
- Revenue per available room
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

Document financial metric formulas.

**Every day at 17:00 Africa/Johannesburg:**

1. Generate and store a daily operational report.
2. Email the owner a secure authenticated link.
3. Send a web push notification.
4. Log delivery status.
5. Retry failed notifications safely.

Do not include unnecessary sensitive employee or guest information in email bodies.

Provide a historical report archive.

Create escalation rules for urgent or unanswered guest requests.

---

# 19. EXTERNAL BOOKING CHANNELS

Approved platforms:

- Booking.com
- LekkeSlaap

Architecture is not yet approved.

Compare:

1. Custom Laravel plus channel manager.
2. Hybrid PMS plus custom Laravel modules.

Create a `ChannelManager` interface supporting:

- Reservation imports
- Availability updates
- Rate updates
- Synchronisation status
- Conflict reporting
- Retry handling

Provide a null development driver.

Investigate iCal import/export as a limited fallback where supported, but do not represent it as equivalent to a full channel manager.

External bookings must receive physical room assignments.

If no valid room is available, place the reservation in an exception queue and alert reception.

Do not silently overbook.

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

Support consent records, access auditing and authorised data exports.

Provide an anonymisation framework, but do not enable irreversible deletion until retention policies are approved.

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

---

# 21. DEVELOPMENT DEMONSTRATION DATA

Create development-only factories and seeders.

Use clearly fictional information for:

- Staff accounts
- Room categories
- Named rooms
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
- Guest portal access
- Employee schedules
- Inspection failures
- Owner reports

Development seed data must never be presented as actual Baseline Lodge information.

Use secure randomly generated development credentials or a clearly controlled local-only authentication setup.

Never deploy predictable demo passwords or demonstration accounts to production.

---

# 22. AUTOMATED TESTING

Write tests covering at minimum:

1. Concurrent bookings for the final available room.
2. Multi-night availability.
3. Room maintenance blocks.
4. Seasonal pricing.
5. Booking status transitions.
6. Payment status transitions.
7. Reservation hold expiry.
8. Duplicate and out-of-order payment webhooks.
9. Manual EFT verification.
10. Dual refund approval.
11. Immutable issued invoices.
12. QR-code privacy.
13. OTP expiry and rate limits.
14. Guest portal session revocation.
15. Checkout balance checks.
16. Duplicate checkout event handling.
17. Housekeeping turnover.
18. Inspection failure and rework.
19. Attendance deduplication.
20. Off-duty employees not being marked absent.
21. Employee access restrictions.
22. Daily report generation at 17:00.
23. Role-based permissions.
24. External booking conflict handling.

Run the relevant tests after each milestone.

Run Laravel Pint and frontend build checks.

Use PostgreSQL for database-dependent integration and concurrency tests so production database behaviour is accurately exercised.

Do not claim test success unless the tests have actually run.

---

# 23. IMPLEMENTATION MILESTONES

Execute the following milestones in order.

## Milestone 1 — Foundation

- Inspect the existing application.
- Verify PostgreSQL.
- Install compatible dependencies.
- Configure authentication.
- Implement roles and permissions.
- Configure administration and staff panels.
- Establish audit logging.
- Create system settings.
- Build base layouts.
- Create documentation.

## Milestone 2 — Accommodation and Booking Engine

- Accommodation categories
- Physical rooms
- Rates
- Seasonal pricing
- Availability service
- Room allocation
- Room-night constraints
- Booking state machines
- Concurrency tests

## Milestone 3 — Public Website

- Homepage
- Room pages
- CMS
- News and events
- Services pages
- SEO
- Responsive design

## Milestone 4 — Reservations and Finance

- Online booking flow
- Guest profiles
- Quotations
- Invoices
- Receipts
- Payment abstractions
- Manual EFT verification
- Refund workflow

## Milestone 5 — Reception

- Reception dashboard
- Booking calendar
- Check-in and checkout
- Guest register
- Key log
- Document QR scanner
- Offline read-only contingency

## Milestone 6 — Messaging and WhatsApp Bookings

- Live price lists
- SMS interface
- WhatsApp interface
- Email templates
- Message logs
- Automation rules

## Milestone 7 — Guest Portal

- Room QR codes
- SMS OTP verification
- Additional authorised guest phones
- Guest service requests
- Guest inbox
- Checkout automation
- Reviews and feedback

## Milestone 8 — Housekeeping and Maintenance

- Cleaning assignments
- Monday deep cleaning
- Inspections
- Rework tasks
- Maintenance
- Stock control

## Milestone 9 — Employees and Attendance

- Employee profiles
- Shift scheduling
- Staff portal
- Attendance events
- Hikvision integration feasibility
- CSV fallback
- Attendance reports

## Milestone 10 — Leisure and Food

- Day passes
- Pool and braai bookings
- VIP areas
- Spa bookings
- Menus
- Kitchen tickets
- Charge-to-room

## Milestone 11 — Management Reporting

- Owner dashboard
- Occupancy analytics
- Financial reporting
- Staff performance reports
- 17:00 daily email
- Push notifications
- Escalation rules

## Milestone 12 — External Channels

- Channel manager abstraction
- Booking.com integration readiness
- LekkeSlaap integration readiness
- Synchronisation monitoring
- Exception handling
- Reconciliation

## Milestone 13 — Final Quality and Documentation

- Compliance configuration
- Security review
- Backup and restore testing
- Performance testing
- Accessibility testing
- Full automated test suite
- Production readiness checklist
- Updated documentation

Do not skip milestone testing.

---

# 24. REQUIRED DELIVERABLES

Produce a working Laravel application accessible through its configured Herd `.test` address.

Maintain:

- `README.md`
- `docs/ARCHITECTURE.md`
- `docs/REQUIREMENTS_TRACEABILITY.md`
- `docs/PENDING_DECISIONS.md`
- `docs/INTEGRATIONS.md`
- `docs/COMPLIANCE_TODO.md`
- `docs/IMPLEMENTATION_PROGRESS.md`
- `docs/TESTING.md`

The README must explain:

- Local installation
- PostgreSQL configuration
- Migrations
- Development seeders
- Queue workers
- Scheduler
- Admin access
- Staff access
- Guest portal
- Integration driver configuration
- Backup and restoration
- Test execution

At the end of each milestone, report:

1. What was implemented.
2. Files created or modified.
3. Database migrations.
4. Available routes.
5. Tests executed and results.
6. Known limitations.
7. Unverified integrations.
8. Pending decisions.
9. Next milestone.

---

# 25. BEGIN DEVELOPMENT NOW

Start with Milestone 1.

Inspect the actual Laravel Herd project and installed environment.

Verify that PostgreSQL is running and that the `baseline_lodge` database is accessible.

If the database does not exist, create it only when authorised and using the configured local PostgreSQL credentials.

Inspect existing migrations and preserve existing data.

Install only compatible dependencies.

Implement:

- Authentication
- Roles and permissions
- Filament administration
- Staff panel foundation
- Audit logging
- Settings management
- Base application layout
- Initial project documentation

Run migrations, tests and code formatting checks.

Then provide a concise implementation report.

Continue through the milestones where requirements are approved and technical dependencies are available. Do not invent missing business decisions or activate real third-party services without approval.

**The objective is to develop a complete, secure, maintainable, commercially deployable Baseline Lodge hospitality platform using Laravel and PostgreSQL, faithfully implementing Specification Version 5.3.**
