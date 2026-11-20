# Baseline Lodge — Final Website & Platform Specification

**Version:** 1.0 (consolidated) · **Date:** 20 November 2026 · **Status:** Build baseline. Items marked PENDING still need client sign-off.

## 0. How to use this document

This is the single consolidated specification for the Baseline Lodge website and management platform. It merges:

| Source | File | Role |
|---|---|---|
| Client requirements v5.3 | `docs/SPEC_v5.3.txt` / `.pdf` | Authoritative business requirements and client decisions |
| Master development prompt | `docs/MASTER_PROMPT.md` | Technical stack and build instructions |
| Baseline team prompt | `docs/source/BASELINE_TEAM_PROMPT.md` | Origin of the 20-page site, roles and the 13 milestones |
| Review and design draft | `docs/source/REVIEW_AND_DESIGN_DRAFT_UNLABELLED.md` | Requirement IDs, glossary, acceptance-criteria format, non-functional targets |

**Order of precedence when sources disagree:**
1. Client decisions in SPEC v5.3 (sections 2, 25–27, 29–39).
2. This specification.
3. `MASTER_PROMPT.md` (technical "how").
4. Reviewer suggestions. These are never approvals on their own.

**Labels used:**
- **CONFIRMED**: approved by the client in v5.3.
- **PENDING**: needs a client decision. Build it as a configurable setting, show "PENDING CLIENT DECISION" in admin, and never hard-code a value.
- **RECOMMENDED**: our proposed default. Build it so it can be switched or changed; the owner may override.
- **VERIFY**: depends on an outside provider, device, or legal or tax advice. Build an interface plus a fake/log driver only.

Each requirement has an ID (e.g. `BOOK-03`) for traceability in `docs/REQUIREMENTS_TRACEABILITY.md`, tests, and change control.

---

## 1. Product summary

Baseline Lodge is a South African guesthouse. The platform is one Laravel application with four areas:

| Area | Path | Users |
|---|---|---|
| Public website | `/` | Visitors and guests booking online |
| Administration | `/admin` | Owner, managers, reception, content editors, administrators |
| Staff portal | `/staff` | Housekeeping, maintenance, inspectors and other employees |
| Guest portal | `/stay` | In-house guests, via room QR + SMS OTP, or a secure SMS link |

**Design principle:** there is exactly one source of availability, the `AvailabilityService`. The website, reception, WhatsApp price lists, and external channels all read and write through it.

**Business defaults:** timezone Africa/Johannesburg (SAST); locale en_ZA; currency ZAR shown as `R 1 234.50`; money stored as integer cents.

---

## 2. Glossary

| Term | Meaning |
|---|---|
| Accommodation type | A sellable category with shared pricing, e.g. "Luxury". Guests book types. |
| Physical room | A specific named room belonging to one type. |
| Room assignment | The audited link between a booking and a physical room, created at booking time. |
| Room night | One room occupied for one night. This is the unit of inventory. |
| Hold | Inventory reserved temporarily, always with an expiry time. |
| Confirmed | Booking accepted under the approved payment/guarantee rule. |
| Verified paid | Funds confirmed by an authorised person or a validated gateway webhook. Proof of EFT alone is never enough. |
| Folio | A guest's running account of charges and payments during a stay. |
| Room ready | Cleaned **and** passed inspection. |
| Stay | The period between check-in and check-out. Guest portal access is tied to it. |
| Day-close | An optional end-of-day freeze of reconciled totals, separate from the 17:00 snapshot. |
| Channel manager | A third-party service that connects to Booking.com, LekkeSlaap and similar sites. |

---

## 3. Decision register

### 3.1 Confirmed client decisions

| ID | Decision |
|---|---|
| DEC-01 | Official name is **Baseline Lodge**. Never "Nyala". |
| DEC-02 | Exact **physical room assigned at booking** for every source, including OTA bookings. If no room can be safely allocated, the booking goes to an exception queue. Never overbook. |
| DEC-03 | Booking.com and LekkeSlaap are approved targets. Airbnb is not in day-one scope. |
| DEC-04 | Keep **both** room QR codes and SMS links. QR access requires a 6-digit OTP to the booking phone. A verified booker may authorise extra guest numbers. |
| DEC-05 | **Instant EFT shown first.** Ordinary EFT and card are also offered. Proof of EFT is not settlement. |
| DEC-06 | Refunds need **two different authorised people** and a full audit trail. |
| DEC-07 | Keep the **Hikvision DS-K1T342MFWX-E1**, with face/fingerprint plus a PIN/M1-card fallback. Biometric templates stay on the device and never enter our database. |
| DEC-08 | Offline mode is a **read-only** cache. No bookings, payments or room allocation while offline. |
| DEC-09 | Attendance and financial **exports only**. No payroll or general ledger. |
| DEC-10 | Owner gets **email with secure link + push at 17:00 SAST** daily. Guests get a thank-you / safe-travels message on completed checkout. |
| DEC-11 | Phased delivery with acceptance gates. Phasing never deletes confirmed scope. |
| DEC-12 | Automated WhatsApp and SMS communication is in scope, subject to consent, templates and a spend cap. |

### 3.2 Pending client decisions

Each one is built as a setting and flagged in admin until decided.

| ID | Decision needed | Interim build behaviour |
|---|---|---|
| PEND-01 | Hold durations, varying by channel or by how close arrival is | Every hold must have an expiry. Self-service holds use a short development default flagged PENDING. Staff choose the expiry for manual holds. |
| PEND-02 | Late EFT after a hold expires | Re-check inventory. If the room is still free, staff may confirm. If not, the booking goes to the exception queue with a rebook-or-refund path. |
| PEND-03 | Order of card vs ordinary EFT after Instant EFT | Configurable order. Default: Instant EFT → Card → Ordinary EFT (from the Baseline team prompt; not a client decision). |
| PEND-04 | Room types, room names, occupancy, rates, seasons | Demo data marked "DEMO — replace before launch". |
| PEND-05 | House rules, check-in/out times, deposit, cancellation, no-show, refund policy | Editable CMS pages and settings with placeholder text marked DEMO. |
| PEND-06 | Who may override a checkout balance, room readiness or a failed inspection | Permission `override.*` granted to Owner only by default. |
| PEND-07 | Providers: payment gateway, SMS/OTP, WhatsApp, channel manager | Interfaces + fake/log drivers. |
| PEND-08 | Architecture: custom Laravel + channel manager vs hybrid PMS | Build custom Laravel behind a `ChannelManager` interface so either path stays open. Record the decision in `docs/ARCHITECTURE.md`. |
| PEND-09 | Budget ceilings and named product approver | Not a build item. Listed in `docs/PENDING_DECISIONS.md`. |
| PEND-10 | Website languages | English only at launch, with the CMS ready for translations. (One review mentions five languages; the client has not confirmed this.) |
| PEND-11 | Retention periods (guest register, attendance, messages, audit) | Configurable. Nothing deleted automatically until approved. |
| PEND-12 | Urgent-request categories and escalation timers | Configurable. Default: 10 min to the backup contact, 30 min to the owner (RECOMMENDED). |
| PEND-13 | Non-functional targets in §20 | Use the RECOMMENDED values as design targets. |
| PEND-14 | Optional extras: alternative OTP channels, OTA inbox, smart locks, vouchers, event packages | Behind feature flags, off by default. |

---

## 4. Users, roles and access

| Role | Main access |
|---|---|
| Administrator | Users, roles, settings, integrations, technical logs |
| Owner | Everything, including approvals, overrides, reports and audit history |
| Manager | Operations, rosters, leave, reports, first refund approval |
| Receptionist | Reservations, check-in/out, payments (record only), documents, guest requests, key log |
| Housekeeping worker | Own tasks and shifts. No payment data. |
| Maintenance worker | Own tasks and shifts. No payment data. |
| Inspector | Assigned inspections, scores, rework |
| Content editor | Website CMS only |
| Guest (no staff account) | Own stay via the guest portal only |

RECOMMENDED: a **Finance** permission set (reconciliation, refunds, exports) that can be given to Owner or Manager accounts. This avoids creating a separate role.

| ID | Requirement |
|---|---|
| SEC-01 | Individual accounts only. No shared logins. |
| SEC-02 | Server-side policies on every model and action. Hiding something in the UI is never the only check. |
| SEC-03 | TOTP 2FA required for Administrator, Owner, Manager and Receptionist. |
| SEC-04 | Login rate limiting, password reset, secure sessions. |
| SEC-05 | Audit log (who, what, when, before/after) for bookings, room assignments, payments, refunds, financial documents, attendance corrections, guest profiles, roles and settings. |
| SEC-06 | Users may hold several roles. Role changes are audited. |
| SEC-07 | Sensitive guest and staff fields are encrypted. Searching them uses keyed lookup hashes. |
| SEC-08 | Uploads: MIME and size checks, private disk, EXIF stripped, antivirus interface (not shown as active until a real scanner is connected). |

**Acceptance:** a Housekeeping user calling a payment URL directly gets 403, and the attempt is logged.

---

## 5. Public website

### 5.1 Pages

| # | Page | Notes |
|---|---|---|
| 1 | Home | See 5.2 |
| 2 | About Us | Kept from the existing site |
| 3 | Our Rooms | List of accommodation types with "from" prices |
| 4 | Room Details | Gallery, amenities, occupancy, rates, availability check |
| 5 | Features & Amenities | Kept from the existing site |
| 6 | Services | Day passes, pool, braai, VIP 1, VIP 2, spa, future offerings |
| 7 | Swimming Pool & Day Passes | |
| 8 | Braai Facilities | |
| 9 | VIP Areas | VIP 1 and VIP 2 |
| 10 | Spa & Massage | Kept from the existing site |
| 11 | News & Events | CMS-driven |
| 12 | FAQs | |
| 13 | Contact Us | Map, form, WhatsApp click-to-chat |
| 14 | Check Availability | |
| 15 | Book Accommodation | See §8 |
| 16 | Guest Portal | Entry and explanation page for `/stay` |
| 17 | House Rules | PENDING content |
| 18 | Booking & Cancellation Policies | PENDING content |
| 19 | Privacy Policy | POPIA notice. VERIFY wording with legal adviser. |
| 20 | Official Communication Policy | "We never ask for card details by WhatsApp, SMS or chat" |

### 5.2 Homepage

Hero image/slider · availability search · featured accommodation with live "from" prices · facilities & services · spa and leisure highlights · latest 3 news posts · moderated guest reviews · contact details · WhatsApp button · booking call-to-action.

### 5.3 Requirements

| ID | Requirement |
|---|---|
| WEB-01 | Mobile-first, responsive, colourful but tasteful. Branding (logo, colours, fonts, imagery) is configurable in admin. |
| WEB-02 | WCAG 2.1 AA. Lighthouse ≥ 90 for Performance, Accessibility, Best Practices and SEO on Home and Room Details (mobile). |
| WEB-03 | Responsive images (`srcset`, lazy loading, modern formats). |
| WEB-04 | SEO: per-page meta, Open Graph, sitemap, structured data (LodgingBusiness, HotelRoom), clean slugs. |
| WEB-05 | Redirect manager for old-site URLs (301). |
| WEB-06 | Prices and availability always come from the rate engine and `AvailabilityService`. Nothing on the website is a hand-typed price. |
| WEB-07 | Tourism grading stars appear only after evidence is uploaded and approved. |
| WEB-08 | No invented facts. Until real content is supplied, all photos, room names, prices, contact details and policies show a visible "DEMO — replace before launch" badge in non-production environments. |
| WEB-09 | Clear result screens for booking success, pending payment, failure and expiry, each with next steps. |
| WEB-10 | Enquiry fallback (form + WhatsApp) for anyone who cannot complete an online booking. |

### 5.4 Content management (CMS)

| ID | Requirement |
|---|---|
| CMS-01 | Editors manage pages, news, events, FAQs, galleries, amenities, site settings, SEO metadata and redirects. |
| CMS-02 | News workflow: Draft → In review → Published → Archived. Content editors submit for review; Manager or Owner publishes. |
| CMS-03 | Preview before publishing. Editor and publish time are recorded. |
| CMS-04 | Latest published posts appear on the homepage automatically. |
| CMS-05 | Ready for translations (PEND-10). Missing translations fall back to English. |
| CMS-06 | Site-wide settings (contact details, banking details, social links) need Administrator or Owner rights. |

---

## 6. Rooms and inventory

| ID | Requirement |
|---|---|
| INV-01 | Separate entities: accommodation types, physical rooms, rate plans, seasonal rates, room blocks, extras, coupons, taxes/fees. |
| INV-02 | Guests book a **type**. The system assigns a **physical room** immediately (DEC-02). Room numbers are not shown publicly before confirmation. |
| INV-03 | Inventory is a `room_nights` ledger with a unique active claim per `(room_id, night_date)`, enforced by PostgreSQL. Released claims (cancelled, expired, moved) are kept as history and never block new claims. |
| INV-04 | Second safeguard: a PostgreSQL exclusion constraint on active room assignments (`daterange` overlap). |
| INV-05 | Maintenance or owner-use blocks claim room nights in the same way and appear on all calendars. |
| INV-06 | Availability is re-checked inside the confirming transaction. Frontend checks are advisory only. |
| INV-07 | Room reassignment needs a reason and permission, is audited, and updates guest-portal access and housekeeping tasks. |

**Acceptance:**
- Two concurrent attempts to book the last free room on separate database connections: exactly one succeeds, the other gets a clear "no longer available" message, and the outcome is logged.
- Cancelling a booking frees its nights immediately for new searches.

---

## 7. Booking engine and statuses

There are three independent state machines. Booking status and payment status are never combined into one field.

| Booking | Payment | Room readiness |
|---|---|---|
| Quote, Hold, Pending, Confirmed, Checked-in, Checked-out, Cancelled, No-show, Expired | Unpaid, Part-paid, Pending verification, Paid to channel, Verified paid, Refund pending, Part-refunded, Refunded, Reconciliation required | Dirty, Cleaning, Awaiting inspection, Ready, Occupied, Out of service |

| ID | Requirement |
|---|---|
| BOOK-01 | Sources: online, reception (walk-in, phone, email), WhatsApp price list, external channels. All use the same `AvailabilityService` and conflict rules. |
| BOOK-02 | Supports multi-room, group bookings, extras, discounts/coupons, special requests, extensions, early checkout, reassignment, cancellation and no-show. |
| BOOK-03 | Invalid transitions are rejected. Full status history is kept. |
| BOOK-04 | Every Hold has `expires_at`. A scheduler job releases expired holds every minute (PEND-01). |
| BOOK-05 | Booking source and external reference are recorded. |
| BOOK-06 | Returning guests are matched to their existing profile instead of retyping details. |

**Acceptance:** an expired hold moves to Expired within 2 minutes, its room nights are released, and a payment arriving afterwards follows PEND-02.

---

## 8. Online booking flow

Search (dates, guests) → available types → choose type → extras (breakfast, dinner, services) → guest details → price summary → payment method → result.

| ID | Requirement |
|---|---|
| OBK-01 | Summary shows nightly breakdown, taxes and fees (if configured), extras, deposit due, total and cancellation policy. |
| OBK-02 | Payment methods in configured order, Instant EFT first (DEC-05, PEND-03). |
| OBK-03 | Booking-terms acknowledgement is required. Marketing consent is a separate, optional, unticked box. |
| OBK-04 | Uploading proof of EFT sets payment to Pending verification. It never sets Verified paid. |

---

## 9. Payments and financial documents

| ID | Requirement |
|---|---|
| PAY-01 | Separate records: quotation, invoice, receipt, credit note, payment, refund, folio. A quotation is not an invoice, and an invoice is not a receipt. |
| PAY-02 | Sequential numbering per document type, PDF generation, banking details, itemised lines, tax lines when configured. |
| PAY-03 | Issued documents are immutable. Corrections use credit notes or audited adjustments. |
| PAY-04 | Quotation → invoice conversion without retyping. |
| PAY-05 | `PaymentGateway` interface + fake driver. Adapters for Ozow, PayFast or Yoco only after selection (VERIFY). RECOMMENDED: start with one gateway. |
| PAY-06 | Webhooks: signature check, idempotency, safe handling of duplicates and out-of-order events, scheduled settlement reconciliation. |
| PAY-07 | Manual EFT stays Pending verification until authorised staff confirm settlement against the bank. |
| PAY-08 | Refund needs two different authorised users. Reason, approvers, gateway reference and reconciliation status are recorded (DEC-06). |
| PAY-09 | No card numbers or CVVs are ever stored. Cards are handled only on the provider's hosted page. |
| PAY-10 | Exceptions queue for failed, duplicate, late, partial and unmatched payments. Reconciliation export (CSV). |

**Acceptance:**
- Sending the same webhook twice records one payment.
- The second refund approval must come from a different user.
- Editing an issued invoice is blocked.

---

## 10. WhatsApp-assisted booking

| ID | Requirement |
|---|---|
| WA-01 | Reception picks dates and generates a branded, secure, expiring price-list link from live availability. |
| WA-02 | The customer selects type/room and extras, enters name, surname and phone, sees an itemised total, and submits. |
| WA-03 | Inventory is re-checked on selection and on confirmation. Opening a link never guarantees availability. |
| WA-04 | The system issues an unpaid invoice with banking details and an optional payment link. A hold applies per PEND-01. |
| WA-05 | On verified payment: invoice marked paid, receipt issued, booking confirmed, reception notified, guest messaged. |
| WA-06 | Document links are sent by WhatsApp; PDFs are attached by email. Every message is logged with delivery status. |

---

## 11. Reception

| ID | Requirement |
|---|---|
| REC-01 | Dashboard: arrivals and departures today, in-house guests, free rooms, balances due, EFTs awaiting verification, holds expiring, open requests, maintenance alerts, conflicts and exceptions. |
| REC-02 | Interactive room-by-date calendar (occupancy, assignments, blocks, arrivals, departures). |
| REC-03 | Create and edit bookings, check in and out, extend, reassign (with reason), add charges, record payments, issue documents. |
| REC-04 | Guest register capture. Required fields are configurable (VERIFY: SA guest-register law). |
| REC-05 | Key/key-card handover and return log, including lost keys. Towels and toiletries issued log. Complaints log. |
| REC-06 | Document QR scanner (browser camera). Shows guest name, room, dates, extras, balance and history, only inside an authenticated reception session. |
| REC-07 | QR codes on documents carry opaque, revocable references and never personal data. |

---

## 12. Offline reception contingency

| ID | Requirement |
|---|---|
| OFF-01 | Authorised devices hold an encrypted, read-only cache of today's and tomorrow's arrivals, the room list and basic status. |
| OFF-02 | Shows the last successful sync time. The cache expires after a short configurable period (RECOMMENDED: 24 h). |
| OFF-03 | No confirmations, payments or room allocation while offline (DEC-08). If secure storage isn't possible on the device, a printable arrivals sheet is used instead. |

---

## 13. Guest portal (room QR + SMS)

| ID | Requirement |
|---|---|
| GST-01 | Each physical room has a unique, rotatable QR token. Scanning it shows a generic page with no guest name or booking details. |
| GST-02 | A 6-digit OTP is sent to the booking phone or an authorised extra number. Only a hash is stored. Expires after 5 minutes; max 5 attempts per code; max 3 sends per 15 minutes per stay (RECOMMENDED limits); anti-enumeration and SMS-pumping alerts. |
| GST-03 | Staff never see OTP values. Reception recovery requires a documented, audited identity check. |
| GST-04 | The verified booker can add and revoke extra guest numbers. All changes are logged. |
| GST-05 | Arrival-day and departure-day SMS links open the same portal. |
| GST-06 | Sessions are tied to the active stay and room. Access is revoked at checkout, on room reassignment or after a security incident. No earlier guest's messages are ever shown. |
| GST-07 | Features: welcome info, Wi-Fi, house rules, emergency contacts, facility hours, menus and meal ordering, towel and toiletry requests, reception inbox, spa, day-pass and VIP bookings, itemised bill, checkout request, reviews and photos (moderated, consent-based). |
| GST-08 | Reception inbox gives audible and visual alerts and escalates if unacknowledged (PEND-12). |
| GST-09 | RECOMMENDED: digital pre-check-in link before arrival, and guest preferences stored only with consent and retention controls (v5.3 §16). |

**Acceptance:**
- Someone holding only the QR image sees no personal data.
- After checkout, the old session cannot place requests.

---

## 14. Messaging and automation

| ID | Requirement |
|---|---|
| MSG-01 | Interfaces for SMS, WhatsApp and email, with log/fake drivers locally. No real sends until a provider is approved and activated. |
| MSG-02 | Templates with channel, language, version, variables, operational/marketing type, cost estimate and idempotency key. |
| MSG-03 | Automations: enquiry acknowledgement, price-list follow-up, unpaid-invoice reminder, payment confirmation, pre-arrival info, arrival-day portal link, departure-day checkout link, thank-you/safe-travels, optional feedback request, staff task notices, owner escalation. |
| MSG-04 | Respect consent, preferences and opt-outs. Marketing messages need marketing consent. |
| MSG-05 | Delivery status, retries with limits, failure log. Staff can resend or share a link by WhatsApp or email if SMS fails. |
| MSG-06 | Monthly spend cap per channel, with owner alerts at 80% and a hard stop for non-critical messages at 100%. |

---

## 15. Checkout and room turnover

| ID | Requirement |
|---|---|
| CHK-01 | Checkout is started from the portal or by reception. The system checks the folio, unpaid extras, unverified EFTs and disputes. |
| CHK-02 | An unresolved balance routes to reception and never completes silently. Zero balance is the default gate; manager override needs a reason and is audited (PEND-06). |
| CHK-03 | On completion: record departure, close the stay, revoke portal access, issue the final receipt, send farewell **once**, create a turnover task, set the room to Dirty, notify the assigned worker. |
| CHK-04 | All of this runs as idempotent jobs. Retries never duplicate messages or tasks. |

---

## 16. Housekeeping, inspections, maintenance

| ID | Requirement |
|---|---|
| HK-01 | Task types: daily clean, light refresh, checkout clean, Monday deep clean, gardening, maintenance, pool, VIP areas, braai area, fish pond, pressure-washing, floor polishing, painting, repairs. |
| HK-02 | Each task has assignee, location, checklist, priority, due time, status, completion evidence and optional photos. |
| HK-03 | Reception assigns housekeeping. Owner or supervisor assigns gardening and maintenance. |
| HK-04 | Monday deep-clean tasks are generated automatically. Checklist: under beds, floors, shower, beds, aluminium windows and doors, mirrors, microwave, kettle, drinking water, toiletries, towels, coffee and sugar. |
| HK-05 | Inspectors score each criterion on a configurable scale. A failed item creates a rework task, notifies the worker and schedules re-inspection. |
| HK-06 | A room becomes Ready only after the clean is done **and** the inspection passes, unless an audited override is recorded. |
| HK-07 | Cleaning effort estimates per room type and task type, for fair rosters. |
| HK-08 | Maintenance: faults per room, area or asset; preventive schedules; asset history; warranties; supplier costs. |
| HK-09 | Stock ledger for towels, toiletries, coffee, sugar and cleaning supplies, with low-stock alerts. |
| HK-10 | RECOMMENDED: lost-property log (found date, place, description, storage, outcome), with restricted access. |

---

## 17. Employees, rosters and attendance

| ID | Requirement |
|---|---|
| EMP-01 | Employee profiles (self-completed, with photo; admin approves), departments, multiple roles. |
| EMP-02 | Weekly rosters by weekday and shift, leave, public holidays, exceptions, cover changes. |
| EMP-03 | Staff see only their own shifts, tasks, notifications, leave and attendance. Owner and authorised managers see everyone. |
| EMP-04 | Off-duty staff are **never** marked absent and don't receive non-urgent notifications. |
| ATT-01 | `AttendanceSource` interface: Hikvision adapter (VERIFY on installed firmware via ISAPI/ISUP), CSV import, authorised manual entry. |
| ATT-02 | Raw punches are stored separately from derived attendance. Idempotent import with composite dedup keys (never sequence numbers alone). |
| ATT-03 | Statuses: Worked, Late, Absent, Incomplete, Off duty, Pending review. Valid clock-in/out windows are set per shift. |
| ATT-04 | Manual corrections need permission and a reason, and are audited. |
| ATT-05 | Monthly payroll-ready export (CSV). No payroll calculation (DEC-09). |
| ATT-06 | `docs/HIKVISION_POC.md`: firmware, network, sample punch, duplicates, outage recovery, timezone, acceptance test. |

---

## 18. Leisure services and food

| ID | Requirement |
|---|---|
| LSR-01 | Bookable services: pool day pass, braai, VIP 1, VIP 2, spa and massage, breakfast, dinner, and others as configured. |
| LSR-02 | Time slots, capacity, opening hours, duration, restrictions, cancellation rules, payments. |
| LSR-03 | Spa bookings check both therapist and treatment-room availability. |
| LSR-04 | Meals: menus, allergens, order cut-off times, kitchen tickets. |
| LSR-05 | In-house guests can charge eligible services to their folio. |
| LSR-06 | Vouchers, packages, event hire and safety/indemnity acknowledgements sit behind feature flags (PEND-14). |

---

## 19. Owner dashboard, reports and external channels

| ID | Requirement |
|---|---|
| REP-01 | Dashboard: occupancy %, ADR, RevPAR, revenue and payments, balances, booking sources, arrivals and departures, attendance, housekeeping, inspection scores, maintenance, complaints, unresolved requests, integration exceptions. |
| REP-02 | Metric formulas are documented. Reports state their date basis (stay date vs transaction date) and range, and keep confirmed, pending, cancelled and no-show bookings separate. |
| REP-03 | 17:00 SAST daily: generate and store the snapshot, email the owner a secure link, send a web push, log delivery, retry failures (DEC-10). Emails contain no unnecessary personal data. |
| REP-04 | Historical report archive. Optional day-close freeze. |
| REP-05 | Urgent-request escalation runs independently of the daily report (PEND-12). |
| REP-06 | Marketing measures: booking conversion, booking source and campaign tracking. No unverified industry benchmarks are shown (v5.3 §16). |
| CHN-01 | `ChannelManager` interface (reservation import, availability and rate push, sync status, conflicts, retries) with a null driver (PEND-07, PEND-08). |
| CHN-02 | iCal import/export as a **limited** fallback, clearly labelled as not a channel manager. |
| CHN-03 | OTA bookings are auto-assigned a room on import or go to the exception queue with an alert (DEC-02). |
| CHN-04 | Stale-sync warnings, scheduled reconciliation, no claims of real-time sync without provider evidence. |

---

## 20. Security, privacy, and non-functional targets

| ID | Requirement |
|---|---|
| PRIV-01 | Collect only what operations need. Role-based access. Access to sensitive records is logged. |
| PRIV-02 | Consent records, preference management, opt-outs respected by all automations. |
| PRIV-03 | Configurable retention per data type, legal-hold exceptions, an anonymisation framework. No irreversible deletion until PEND-11 is approved. |
| PRIV-04 | `docs/COMPLIANCE_TODO.md` lists VERIFY items: guest register, POPIA, PAIA, VAT/tax invoices, biometrics, labour records, tourism grading, payment security. |
| PRIV-05 | Backups encrypted to independent storage (spatie/laravel-backup). The restore procedure is in `docs/BACKUP_RESTORE.md` and tested in Milestone 13. |

RECOMMENDED non-functional targets (PEND-13):

| Area | Target |
|---|---|
| Availability | 99.5% monthly for booking and staff functions |
| Staff screens | ≤ 2 s at the 95th percentile under normal load |
| Public pages | Lighthouse ≥ 90 (mobile); LCP ≤ 2.5 s |
| Backups | RPO 4 h (database), RTO 8 h; daily off-site copy |
| Browsers | Current Chrome, Edge, Safari, Firefox; Android and iOS mobile |
| Monitoring | Alerts for integration failures, failed jobs, repeated booking errors |

---

## 21. Technical stack (summary)

Laravel 13 · PHP 8.4+ (currently 8.5 via Herd) · **PostgreSQL only, tests included** · Filament (`/admin`, `/staff`) · Livewire 3 + Blade + Tailwind + Alpine, built with Vite (`/`, `/stay`) · Fortify + TOTP · spatie/laravel-permission, activitylog, medialibrary, sitemap, sluggable, backup · dompdf · simple-qrcode · FullCalendar widget · web push · Pest · Pint · database queue · `routes/console.php` scheduler.

Full detail, conventions and package-compatibility rules are in `docs/MASTER_PROMPT.md` §0–0A.

---

## 22. Delivery plan

| Milestone | Content | v5.3 phase |
|---|---|---|
| M1 Foundation | PostgreSQL, auth + 2FA, roles, panels, audit, settings, layouts, docs | 1 |
| M2 Rooms & booking engine | Types, rooms, rates, `AvailabilityService`, room nights, state machines, concurrency tests | 1 |
| M3 Public website | 20 pages, CMS, SEO, redirects | 1 |
| M4 Reservations & finance | Booking flow, profiles, documents, payments, EFT verification, refunds | 1 |
| M5 Reception | Dashboard, calendar, check-in/out, register, key log, scanner, offline | 1 |
| M6 Messaging & WhatsApp | Price lists, SMS/WhatsApp/email, logs, automations, spend cap | 1 |
| M7 Guest portal | Room QR, OTP, extra phones, requests, inbox, checkout, reviews | 2 |
| M8 Housekeeping & maintenance | Tasks, Monday deep clean, inspections, rework, maintenance, stock | 2 |
| M9 Employees & attendance | Profiles, rosters, staff portal, attendance, Hikvision feasibility, CSV | 3 (feasibility tested early) |
| M10 Leisure & food | Day passes, braai, VIP, spa, menus, kitchen tickets, charge-to-room | 2 |
| M11 Reporting | Owner dashboard, analytics, 17:00 report, push, escalations | 1 (17:00 report) / 3 |
| M12 External channels | Channel-manager abstraction, readiness, monitoring, reconciliation | after PEND-08 |
| M13 Final quality | Compliance config, security, backup restore, performance, accessibility, full tests, production checklist | all |

Each milestone ends with migrations, Pest on PostgreSQL, Pint, a frontend build, and an update to `docs/IMPLEMENTATION_PROGRESS.md`.

**Before go-live (v5.3 §21, §38):**
- Migrate content and URLs from the existing website, with 301 redirects.
- Import historical guest, booking and financial records from the current system and verify them.
- Run the old and new systems in parallel during a pilot.
- Complete UAT, staff training and admin manuals.
- Confirm the guesthouse owns the domain, hosting, repository, payment and messaging accounts.

---

## 23. Global acceptance criteria

The platform is ready for handover only when all of these have been demonstrated:

1. Every booking source goes through the same inventory conflict checks. No two active assignments ever overlap for one physical room, including under concurrent attempts.
2. Booking, payment and room-readiness statuses are separate and correct.
3. Manual EFT verification, duplicate/out-of-order webhooks, late payments and dual-approval refunds pass their tests.
4. The QR image alone reveals nothing. Portal access ends at checkout or reassignment. OTP limits are enforced.
5. Clean → inspect → fail → rework → re-inspect → Ready works end to end.
6. Off-duty staff are never marked absent. Duplicate attendance imports have no effect.
7. Role permissions and audit logs are demonstrated for every sensitive action.
8. The 17:00 report generates, delivers, logs and retries correctly.
9. A backup restore has been tested and documented.
10. No DEMO content, demo accounts or fake drivers are active in production. All PENDING items are either decided or still visibly flagged.

---

## 24. Required project documents

`README.md` · `docs/ARCHITECTURE.md` · `docs/REQUIREMENTS_TRACEABILITY.md` (using the IDs in this file) · `docs/PENDING_DECISIONS.md` (seeded from §3.2) · `docs/INTEGRATIONS.md` · `docs/COMPLIANCE_TODO.md` · `docs/IMPLEMENTATION_PROGRESS.md` · `docs/TESTING.md` · `docs/HIKVISION_POC.md` · `docs/BACKUP_RESTORE.md`.
