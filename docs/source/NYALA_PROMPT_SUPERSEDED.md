<!--
SUPERSEDED — kept for reference only. Do not build from this file.
Source: pasted into the claude.ai chat "Laravel Herd guesthouse website prompt" as
"Pasted text.txt" (19.9 kB, 697 lines); the user later said this was the wrong prompt.
Copied verbatim on 2026-11-20. The official name is Baseline Lodge, never "Nyala".
Note: its payment order (Instant EFT, then ordinary EFT, then cards) is closer to
SPEC v5.3 than the Baseline team prompt's order.
-->

# MASTER DEVELOPMENT INSTRUCTION — NYALA GUESTHOUSE

## Project: Nyala Guesthouse Management Platform
**Specification:** Consolidated Requirements Version 5.3  
**Framework:** Laravel 13, subject to compatibility verification  
**Database:** PostgreSQL  
**Development environment:** Laravel Herd / Laravel Studio  
**Frontend:** Modern responsive Laravel-compatible frontend  
**Project name:** Nyala Guesthouse

You are the principal Laravel architect, senior full-stack developer, database engineer, UI/UX designer, security engineer and software testing specialist responsible for developing the Nyala Guesthouse Management Platform.

Your assignment is to build a modern, professional, secure and scalable guesthouse website integrated with a comprehensive property management system.

**The attached document, "Guesthouse Management Platform — Consolidated Requirements Version 5.3", is the authoritative project specification.**

Read the entire document before development. Preserve every confirmed requirement, including the original requirements incorporated into Version 5.3.

Do not silently remove, simplify or postpone approved functionality.

## 1. INITIAL PROJECT INSTRUCTIONS

Before writing application code:

1. Inspect the existing Laravel project, if one exists.
2. Identify the installed Laravel, PHP, Composer, Node.js and frontend versions.
3. Check existing routes, controllers, models, migrations, tests and dependencies.
4. Determine whether the application already contains guesthouse-related functionality.
5. Preserve existing functionality and data.
6. Establish the PostgreSQL database connection.
7. Prepare a development roadmap based on Version 5.3.
8. Identify approved requirements, recommendations, unresolved decisions and external dependencies.
9. Establish a Git baseline before major modifications.
10. Run existing tests and record the baseline results.

If this is a new project, create the application using supported stable dependencies.

Use Laravel 13 where compatible with the development environment. Do not force incompatible package versions.

Do not automatically delete databases, reset existing production data, or overwrite working modules.

## 2. TECHNOLOGY ARCHITECTURE

Use the following technical foundation:

- Backend: Laravel 13
- Language: PHP 8.4 or compatible supported version
- Database: PostgreSQL
- ORM: Laravel Eloquent
- Administration: Filament, using a compatible stable release
- Interactive interfaces: Livewire where appropriate
- Frontend styling: Tailwind CSS
- Asset compilation: Vite
- Authentication: Laravel-compatible secure authentication
- Permissions: Role-based access control
- Background jobs: Laravel queues
- Scheduled tasks: Laravel Scheduler
- Automated testing: Pest or PHPUnit
- Source control: Git
- API integrations: Secure, documented service adapters

Use PostgreSQL as the primary relational database for all core business records.

Use database transactions, foreign keys, indexes, constraints and appropriate locking.

Store financial amounts using exact decimal or integer-minor-unit representations. Never use floating-point arithmetic for money.

Store timestamps consistently and display operational times in Africa/Johannesburg.

Do not assume Redis, S3, payment gateways or third-party services are already configured. Make integrations configurable.

## 3. APPLICATION DESIGN

Create two connected experiences.

### A. Public Nyala Guesthouse Website

Build an attractive, mobile-first hospitality website with:

- Home
- About Us
- Our Rooms
- Features and Amenities
- Services
- Spa
- News and Events
- Contact Us
- Room Availability and Booking
- Guest Portal
- Privacy Policy
- Booking Terms and Conditions

The website should have professional navigation, beautiful layouts, clear booking actions, responsive images and fast loading.

Use configurable branding, colours, photographs and content.

Do not invent the guesthouse's real address, contact details, prices, facilities, policies or photographs.

Use clearly labelled sample content until actual information is supplied.

### B. Internal Management Platform

Build a secure administration system containing:

- Management dashboard
- Reception dashboard
- Booking calendar
- Room inventory
- Guest profiles
- Reservations
- Quotations
- Invoices
- Receipts
- Payments
- WhatsApp and SMS communication
- QR-code guest services
- Housekeeping
- Room inspections
- Maintenance
- Employee profiles
- Shift scheduling
- Attendance
- Guesthouse services
- Day passes
- Spa bookings
- News CMS
- Notifications
- Owner reports
- System configuration
- Audit logs

Each user must see only the functions permitted by their assigned role.

## 4. ACCOMMODATION AND BOOKING ENGINE

Implement accommodation categories and individual physical rooms as separate entities.

Each accommodation category must support:

- Name and description
- Capacity
- Amenities
- Photographs
- Base rates
- Seasonal rates
- Additional guest charges
- Availability
- Applicable taxes and fees

Each physical room must have a unique identity.

**Mandatory approved rule: Allocate an exact physical room when a booking is created.**

Bookings may be priced and sold by room category, but every accepted reservation must receive an individual physical room assignment immediately.

Use PostgreSQL transactions and appropriate constraints to prevent overlapping room assignments.

Support:

- Direct website bookings
- Reception bookings
- WhatsApp-assisted bookings
- Returning guest bookings
- Group bookings
- Booking amendments
- Stay extensions
- Room transfers
- Maintenance blocks
- Cancellation and no-show workflows
- Booking history
- Booking conflict alerts

Keep booking status, payment status and room-cleaning/readiness status separate.

Do not allow a room to be double-booked.

## 5. EXTERNAL BOOKING CHANNELS

The two approved target platforms are:

- Booking.com
- LekkeSlaap

Do not assume direct API integration is available.

Implement an integration architecture that can accommodate a supported channel manager.

During technical discovery, compare:

1. Custom Laravel operations with a commercially supported channel manager.
2. Hybrid PMS/channel manager with custom Laravel modules.

Investigate NightsBridge and other suitable providers without selecting one prematurely.

Create integration interfaces, synchronisation logs, retry handling, inventory reconciliation and conflict reporting.

Do not claim an external channel is connected until real integration testing succeeds.

Airbnb is not an approved initial integration.

## 6. WHATSAPP-ASSISTED RESERVATIONS

Build the WhatsApp booking workflow described in Version 5.3.

Reception must be able to generate a colourful, branded availability and price-list link.

The link must retrieve room availability from the central booking calendar.

Customers must be able to:

- View available rooms for selected dates
- Select their preferred accommodation
- Enter name, surname and phone number
- Add breakfast, dinner or other services
- View the complete price
- Submit a reservation request
- Receive an unpaid invoice or payment request
- Pay using available payment methods
- Receive confirmation after verified payment

Send secure document download links through WhatsApp.

Send actual PDF invoices through email.

All requests must be visible in the reception dashboard.

Reservation hold durations remain pending client approval. Implement configurable policies without inventing a final duration.

## 7. QUOTATIONS, INVOICES AND PAYMENTS

Build a complete financial document workflow supporting:

- Quotations
- Unpaid invoices
- Paid invoices
- Receipts
- Credit notes
- Refund records
- Payment history
- Outstanding balances
- Guest folios
- Financial exports

Quotations must be convertible into invoices without retyping customer information.

Confirmed payments must produce appropriate receipts.

Retain immutable records of issued financial documents. Corrections must be traceable.

Payment options:

1. Instant EFT — preferred display option
2. Ordinary EFT
3. Bank cards

Integrate payment gateways only after provider selection.

Manual EFT proof of payment must not automatically mark an invoice as paid.

Implement payment verification, webhook signature checks, idempotency, settlement reconciliation and audit trails.

Refunds require dual approval by authorised personnel.

## 8. QR-CODE GUEST SERVICES

Every physical room must have a unique QR code.

Scanning the code opens a guest verification page.

Before accessing private room services:

1. Identify the active checked-in stay.
2. Send a six-digit SMS OTP to the registered booking number.
3. Require OTP verification.
4. Expire OTPs after five minutes.
5. Apply request limits and anti-abuse protection.
6. Create a secure session associated with the active stay.

The verified primary booker may authorise additional guest phone numbers.

Guests must not access another guest's private information.

Automatically revoke stay-bound access when checkout is completed or when access is otherwise invalidated.

The guest portal must support:

- Food and drink menus
- Meal ordering
- Towel and toiletry requests
- Reception assistance
- Guest feedback
- Guest photographs and reviews
- Checkout requests
- Authorised booking information

Room QR codes and secure SMS links must connect to the same guest services system.

## 9. RECEPTION QR SCANNER

Implement a separate authenticated reception scanning feature.

Reception must be able to scan QR codes printed on or displayed with guest invoices and booking documents.

After scanning, display a prominent guest information page containing:

- Guest name
- Contact information
- Booking details
- Assigned room
- Arrival and departure dates
- Payment status
- Breakfast and other purchased services
- Outstanding balance
- Previous booking history where authorised

Reception actions must include:

- Record payments
- Generate invoices and receipts
- Extend stays
- Create new bookings
- Record key handovers and returns
- Record towel or toiletry requests
- Add authorised services
- View previous reservations

Historical invoice QR codes must remain usable for authenticated reception lookup, subject to retention rules.

Do not expose private guest records to unauthenticated QR scanners.

## 10. CHECKOUT AUTOMATION

Guests may request checkout through the verified QR portal or secure SMS link.

Before checkout, validate outstanding balances and unresolved charges.

Where staff review is necessary, create a reception task.

Once checkout is completed:

1. Record departure.
2. Close the active stay.
3. Revoke room-service access.
4. Generate the final receipt where appropriate.
5. Send an automatic thank-you message.
6. Wish the guest safe travels.
7. Create a housekeeping turnover task.
8. Mark the room as requiring cleaning.
9. Notify the responsible employees.

Avoid duplicate messages or housekeeping tasks when background jobs retry.

## 11. HOUSEKEEPING AND ROOM INSPECTIONS

Build employee task allocation, cleaning checklists and inspection workflows.

Support routine cleaning, room refreshing, checkout cleaning and Monday deep cleaning.

Monday deep-clean requirements include:

- Floors
- Showers
- Beds
- Under-bed cleaning
- Windows
- Aluminium doors
- Mirrors
- Microwaves
- Kettles
- Water dispenser or drinking-water replacement
- Toiletries
- Towels
- Coffee and sugar replenishment

Inspectors must be able to:

- Score checklist items
- Record pass/fail results
- Add photographs
- Request corrections
- Order re-inspections
- Generate inspection reports
- Notify assigned workers

Managers must be able to track unresolved inspection failures.

## 12. EMPLOYEE MANAGEMENT AND ATTENDANCE

Create employee profiles, roles, departments, shift schedules and attendance reporting.

**Confirmed existing attendance device: Hikvision DS-K1T342MFWX-E1.**

This machine must be retained.

Supported authentication methods include face, fingerprint, M1 card and PIN.

Investigate ISAPI/ISUP integration using the installed device and firmware.

Do not assume attendance API integration is already verified.

Store attendance events, not biometric templates, in PostgreSQL.

Maintain raw attendance punches separately from calculated attendance.

Attendance calculations must consider assigned shifts, days off, approved leave, late arrivals and missing punches.

An employee must not be marked absent on a day when they were not scheduled to work.

Provide PIN/card alternatives without unfair treatment.

Generate monthly attendance reports and payroll-ready exports.

Full payroll processing is outside the initial approved scope.

## 13. GUESTHOUSE SERVICES

Implement configurable services including:

- Swimming pool day passes
- Braai facilities
- VIP Area 1
- VIP Area 2
- Spa and massage treatments
- Breakfast
- Dinner
- Additional guest services

Support service descriptions, photographs, prices, capacity, availability, bookings and payment records.

Services must be manageable from the administration dashboard.

## 14. NEWS AND CONTENT MANAGEMENT

Create a simple content management system.

Authorised employees must be able to publish:

- Guesthouse news
- Upcoming events
- Special announcements
- Promotions
- Guesthouse updates

Support draft, review, publish and archive states.

Automatically display selected recent articles on the public home page.

## 15. OWNER DASHBOARD AND REPORTS

Create a management dashboard containing:

- Occupancy
- Reservations
- Revenue
- Payments
- Outstanding balances
- Arrivals and departures
- Employee attendance
- Housekeeping performance
- Room inspections
- Maintenance
- Guest complaints
- Unresolved tasks

**Every day at 5:00 PM South African time, automatically send the owner:**

1. A push notification summarising the day's operations.
2. An email containing a secure link to the detailed daily report.

The report must be accessible only to authorised users.

Urgent unanswered guest requests must trigger escalation notifications independently of the daily summary.

## 16. COMMUNICATION AUTOMATION

Build extensive configurable WhatsApp, SMS and email automation.

Support:

- Booking confirmations
- Payment reminders
- Unpaid invoice notifications
- Arrival instructions
- Pre-check-in links
- Guest service notifications
- Checkout reminders
- Thank-you and safe-travels messages
- Staff task notifications
- Inspection notifications
- Owner alerts

Include message templates, delivery tracking, retries, provider configuration, cost monitoring and monthly spending limits.

Do not send live messages until credentials, templates, permissions and provider integrations have been configured and tested.

## 17. SECURITY AND COMPLIANCE

Implement:

- Role-based access control
- Secure authentication
- Appropriate multi-factor authentication
- Audit logging
- Data encryption where appropriate
- Rate limiting
- Secure file storage
- Backup and restoration procedures
- Financial approval controls
- Privacy notices
- Configurable retention rules
- POPIA-aware personal information handling

South African guest-register, tax, labour and privacy requirements must be verified before production.

Do not hardcode unverified legal requirements.

## 18. POSTGRESQL DATABASE DESIGN

Design a properly normalised PostgreSQL schema.

Anticipated modules include:

- Users and roles
- Employees
- Guests
- Guest preferences
- Accommodation types
- Rooms
- Room assignments
- Rates and availability
- Reservations
- Reservation holds
- Folios
- Quotations
- Invoices
- Receipts
- Payments
- Refunds
- Credit notes
- Guest stays
- QR verification sessions
- OTP records
- Guest requests
- Housekeeping tasks
- Inspections
- Maintenance
- Shift schedules
- Attendance events
- Attendance summaries
- Services
- Service bookings
- News articles
- Notifications
- Message logs
- Integration events
- Audit logs

These are proposed entities, not a mandatory one-table-per-item design.

Analyse relationships carefully before creating migrations.

Use appropriate database constraints and indexes.

## 19. DEVELOPMENT PHASES

### Phase 0 — Technical Discovery

Inspect the project and database.

Design architecture, relationships, permissions and major workflows.

Evaluate channel-manager connectivity.

Test Hikvision attendance integration feasibility.

Investigate payment gateways, SMS and WhatsApp providers.

Prepare architecture decisions and a technical proof of concept.

### Phase 1 — Core Guesthouse Platform

Develop:

- Public website
- Content management
- Room categories and physical rooms
- Central booking calendar
- Reception dashboard
- Guest profiles
- Direct reservations
- Invoices and receipts
- Payment recording
- WhatsApp-assisted price lists
- Basic automated communication
- Owner dashboard and 5 PM reporting

### Phase 2 — Guest Experience and Operations

Develop:

- QR-code guest portal
- SMS verification
- Guest service requests
- Automated checkout
- Housekeeping
- Inspections
- Maintenance
- Day passes
- VIP and spa services
- Guest communication escalation

### Phase 3 — Workforce and Advanced Features

Develop:

- Production Hikvision attendance integration
- Employee shift scheduling
- Attendance reporting
- Advanced analytics
- Guest preferences
- Advanced pricing
- Operational optimisation

These phases determine delivery order, not cancellation of approved features.

## 20. TESTING REQUIREMENTS

Write automated tests for important business rules.

Prioritise:

- Double-booking prevention
- Concurrent room reservations
- Physical room assignment
- Payment status transitions
- Duplicate payment webhooks
- QR authentication
- OTP expiry and rate limits
- Checkout balance verification
- Employee shift and absence calculations
- Housekeeping notifications
- Role permissions
- Report generation

Run tests after each development milestone.

Do not describe untested features as production-ready.

## 21. IMPLEMENTATION RULES

Follow these instructions throughout development:

1. Use Version 5.3 as the source of truth.
2. Preserve approved requirements.
3. Do not invent guesthouse-specific information.
4. Do not treat reviewer recommendations as client approvals.
5. Do not activate unverified third-party integrations.
6. Build reusable, maintainable Laravel modules.
7. Use PostgreSQL consistently.
8. Implement business logic on the server.
9. Write tests for critical workflows.
10. Keep documentation updated.
11. Avoid destructive changes without explicit approval.
12. Complete one milestone before proceeding to the next.
13. Maintain a register of pending decisions.
14. Never claim a feature is complete until it is implemented and tested.

## 22. START DEVELOPMENT NOW

Begin by:

1. Auditing the existing project or creating a new Laravel project.
2. Verifying PostgreSQL connectivity.
3. Establishing the application structure.
4. Designing the initial database schema.
5. Creating authentication and role-based permissions.
6. Building the administration dashboard foundation.
7. Creating accommodation types and individual rooms.
8. Implementing the initial booking calendar and room assignment logic.
9. Creating the public Nyala Guesthouse homepage.
10. Writing and running the initial tests.

Produce a concise implementation report showing:

- What was created
- Database migrations
- Models and relationships
- Routes and dashboards
- Tests executed and results
- Outstanding technical dependencies
- Next development milestone

**Do not attempt to implement the entire system in one uncontrolled operation.**

Start with the foundation and the first working vertical slice, then proceed systematically through the approved development phases.

The goal is a professional, secure, maintainable and commercially deployable Nyala Guesthouse Management Platform.
