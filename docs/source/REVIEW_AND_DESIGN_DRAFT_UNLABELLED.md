<!--
Pasted by the user into the Claude Code session on 2026-11-20 without a title.
Saved verbatim (headings re-marked as Markdown, the role table rebuilt from tab-separated text).
Reference only: NOT a client-approved source. SPEC v5.3 governs where they differ.

It has two parts: (1) a review of an earlier spec version (it says the copy it reviewed
"ends at Section 23"), and (2) a "Product and Solution Design Specification" draft produced
in response to the prompt "include your own recommendations and generate a design ready document".
Which reviewer or version it belongs to is unconfirmed.
-->

# Part 1 — Review

## Overall assessment

This is a strong, unusually thorough discovery and stakeholder-review document. It does a good job preserving the original scope, separating confirmed requirements from recommendations, and calling out privacy, payment, integration, and operational risks.

My main concern: it is not quite ready to serve as a final approval document or a build-ready specification. The scope is broad, several decisions remain open, some questions appear to have been superseded by later decisions, and most requirements still need measurable acceptance criteria.

## What's working well

- Good scope coverage: It connects the public website, reservations, reception, guest services, housekeeping, maintenance, attendance, and reporting.
- Useful status distinctions: "Confirmed," "recommended," and "open" help prevent suggestions from quietly becoming commitments.
- Strong security thinking: The room QR does not reveal guest identity, OTPs and per-stay access are specified, and sensitive actions require permissions and audit logs.
- Operational details are concrete: Turnover, inspections, rework, staff rosters, and task evidence are more useful than vague feature labels.
- Commercial and delivery risks are acknowledged: The architecture comparison, three-year cost estimate, account ownership, backups, training, and handover requirements are all important.
- It correctly avoids treating integrations and legal claims as guaranteed.

## Important issues to resolve before approval

### 1. Reconcile stale questions with newer decisions

Section 22 appears to repeat questions that Section 2 and later sections have already answered. For example:

- It asks which attendance device is required, although the Hikvision device is identified as confirmed.
- It asks who may receive the QR OTP, although the document says the OTP goes to the booking phone and the verified booker may authorize additional numbers.
- It asks which payment methods are offered, although several are already listed as approved.

Update or remove these questions, and make the decision register the single source of truth. Also, the text refers to Sections 25–27, but the supplied excerpt ends at Section 23; verify that the complete document includes those sections.

### 2. Clarify physical-room allocation for online and OTA bookings

The requirement to assign an exact physical room at booking is clear for reception, but may conflict with how booking channels manage inventory—often by room type rather than named room. Confirm with the selected PMS/channel manager whether exact assignments can be synchronized reliably.

I'd also distinguish between:

- what the guest selects or sees publicly—usually a room type; and
- the physical room assigned internally.

This will help avoid overselling, unnecessary room changes, or exposing room details prematurely.

### 3. Define booking holds and payment edge cases

The document correctly marks hold duration as pending, but this decision affects the core booking flow. Specify what happens when:

- a hold expires while the guest is paying;
- an EFT arrives after expiry;
- payment is partial or duplicated;
- two guests attempt to book the last available room;
- a booking is cancelled, changed, or marked no-show.

These should become explicit rules with acceptance tests, not just general workflow descriptions.

### 4. Make "approved" requirements testable

Many requirements describe the desired outcome but not how acceptance will be judged. For example:

- What does "fast loading" mean?
- How quickly must a room become available after cancellation or checkout?
- How many failed OTP attempts trigger a lockout?
- How quickly must urgent guest requests escalate?
- What must happen if the Hikvision device or an OTA connection is offline?

Add acceptance criteria to each major requirement. A simple format would be:

> Requirement: The system must prevent overlapping confirmed assignments for the same room.
> Acceptance: When two users attempt to confirm overlapping reservations for that room, only one succeeds; the other receives a clear conflict message. The result is logged.

### 5. Separate scope, policy, and technical design

The document currently mixes product requirements, operating policies, technical controls, legal validation, and implementation options. Consider splitting it into:

- Decision and policy register
- Functional requirements
- Security and non-functional requirements
- Integration and architecture discovery
- Delivery phases and acceptance criteria
- Appendices: source history, legal questions, and detailed task catalogues

That will make it easier for the owner to approve policies, for an agency to estimate work, and for developers to implement the right behavior.

### 6. Resolve the status of recommendations

Some recommendations have significant workflow consequences. For example, requiring a zero balance before checkout is described as a recommended default, while manager overrides are suggested. Make clear whether this is:

- a confirmed operating policy;
- a proposed policy awaiting owner approval; or
- a configurable system rule.

The same applies to events, vouchers, packages, food-order features, and post-stay review requests.

## Further improvements

- Add requirement IDs such as BOOK-01, PAY-04, and GUEST-08 for traceability, testing, and change control.
- Add a glossary for terms such as hold, confirmed, verified paid, room ready, system of record, and day-close.
- Specify key non-functional targets: uptime, backup frequency, restore expectations, response times, supported devices/browsers, and data-retention periods.
- Clarify reporting calculations: occupancy, revenue, ADR, RevPAR, payment timing, cancellations, and whether figures are based on stay date or transaction date.
- Name the WhatsApp/SMS and payment integration assumptions as items to validate, including provider pricing, message-template approval, delivery failure handling, and data ownership.
- Make consent and privacy workflows operational: who records consent, where preferences are stored, how opt-outs affect automated messages, and how retention/deletion requests are handled.
- Review the final layout: the repeated document headers and broken line wraps appear to be extraction or formatting artifacts. The numbered steps also run across sections in a way that may make review harder.

## Bottom line

I'd keep this as the master discovery and scope record, but I would not yet call it a final build specification. Before requesting a fixed-price implementation quote, I'd reconcile the stale decisions, confirm the OTA/room-allocation approach, settle booking and payment rules, and add acceptance criteria to the highest-priority workflows.

---

> include your own recommendations and generate a design ready document

---

# Part 2 — Guesthouse Management Platform: Product and Solution Design Specification

**Document status:** Design-ready draft
**Purpose:** Define the recommended product scope, operating rules, core workflows, security requirements, and acceptance criteria for design and implementation planning.
**Basis:** The supplied stakeholder review and the recommendations below.

## Decision labels

- **Confirmed:** Explicitly established in the supplied material.
- **Recommended:** My proposed default for a safe, workable design; owner approval is still required.
- **Open:** Must be decided or validated before implementation.

## 1. Executive summary

The platform will support the guest journey and day-to-day operation of a guesthouse through a public website and a secured staff system. Its primary capabilities are:

- Public property information and direct reservations.
- Reservation, room-inventory, payment, and guest-record management.
- Reception workflows, including check-in, check-out, invoices, and walk-ins.
- In-stay guest services accessed through a room QR code.
- Housekeeping, room inspections, maintenance, and lost-property workflows.
- Staff roles, attendance integration, operational dashboards, and reporting.
- Connections to payment services, messaging channels, attendance hardware, and—subject to validation—online travel agencies (OTAs).

**Recommended design principle:** The platform should have one authoritative reservation and inventory record. Direct bookings, reception bookings, and external-channel bookings must feed into a controlled inventory workflow to reduce double bookings.

**Important boundary:** This document is ready to guide product and solution design, but vendor selection, integration feasibility, final policies, legal review, and agreed acceptance criteria are still required before a fixed-price implementation commitment.

## 2. Product objectives

The system should:

- Make the guesthouse's rooms and services easy to discover and book.
- Reduce manual reservation, payment, housekeeping, and reporting work.
- Give staff a shared, current view of room status and guest requirements.
- Prevent overlapping room assignments and unauthorized guest-data access.
- Give guests a simple, private way to request services during their stay.
- Provide management with reliable operational and financial summaries.
- Preserve a clear audit trail for important actions and changes.

### Success measures to confirm

The owner should approve targets for:

- Direct-booking completion rate.
- Reservation errors and double-booking incidents.
- Time required to reconcile payments.
- Time from checkout to room-ready status.
- Guest-service response and completion times.
- Accuracy and timeliness of daily reports.
- Staff adoption and training completion.

## 3. Scope

### 3.1 In scope

- Public website with property information, room types, facilities, policies, and contact details.
- Direct booking enquiry and reservation journey.
- Availability and rate management.
- Reception management of online, telephone, email, walk-in, and other approved bookings.
- Guest and stay records, subject to privacy controls.
- Payment tracking, invoice generation, and payment reconciliation.
- Guest service requests using a room QR code and verification flow.
- Housekeeping, inspections, maintenance, and lost-property processes.
- User roles, permissions, audit logging, and staff activity records.
- Operational and management reports.
- Integration discovery and, where validated, integration with OTAs, payment services, messaging, and the attendance device.

### 3.2 Out of scope unless separately approved

- A full accounting or payroll system.
- A proprietary payment-processing service.
- A custom-built OTA distribution network.
- Biometric identification or facial recognition.
- Automated legal or tax advice.
- A full restaurant point-of-sale system.
- A native mobile app, unless needed after web-based workflows are tested.

**Recommendation:** Deliver a responsive web application first. This is likely to cover the public website, staff console, and guest QR service flows without the cost of separate mobile apps.

## 4. Users and permissions

Access must follow least-privilege principles. Users should see and change only the information required for their duties.

| Role | Typical access |
|---|---|
| System administrator | User accounts, roles, configuration, integrations, technical logs. Guest financial or identity data only when operationally necessary. |
| Owner/manager | Reservations, room and rate management, reports, approvals, operational oversight, and audit history. |
| Reception | Reservations, guest records needed for the stay, check-in/out, payments, invoices, and service coordination. |
| Housekeeping | Assigned rooms, cleaning status, inspection results, and relevant guest requirements. No payment details. |
| Maintenance | Assigned faults, room/equipment context, task updates, and completion evidence. No unnecessary guest or payment data. |
| Service staff | Assigned guest requests and fulfilment updates. No full guest profiles or payment data. |
| Finance user | Payment and invoice information, reconciliation, refunds, and financial reports. |
| Guest | Their reservation information through an approved verification process and the service-request functions available during their stay. |

### Access requirements

- **SEC-01:** Require individual user accounts; shared staff logins must not be used.
- **SEC-02:** Enforce role-based permissions for viewing, creating, editing, exporting, and deleting data.
- **SEC-03:** Restrict sensitive operations—such as refunds, permission changes, and manual payment overrides—to authorized roles.
- **SEC-04:** Record the user, action, date/time, and relevant record for sensitive changes.
- **SEC-05:** Review and remove access promptly when a staff member leaves or changes role.
- **SEC-06:** Where supported, require multi-factor authentication for privileged accounts.

## 5. Core operating rules

The following rules are the recommended foundation for the design. Items marked Open require owner confirmation.

### 5.1 Rooms and inventory

- **Confirmed:** The system must manage room availability and physical room assignments.
- **Recommended:** Guests select a room type on public booking channels. Staff may assign a specific physical room internally.
- **Recommended:** A confirmed reservation must not overlap another confirmed reservation for the same physical room.
- **Open:** Can the chosen booking and channel-management products support the required inventory model and room assignment behavior?
- **Open:** How should maintenance blocks, owner use, upgrades, or room closures affect inventory?

**Design requirement:** The system should keep room type, physical room, and reservation inventory as distinct concepts. This supports OTA distribution and prevents a specific room number from being exposed unnecessarily.

### 5.2 Reservation states

A reservation must have one clearly defined state. Recommended states:

- **Enquiry** — requested dates or details have not yet been committed.
- **Hold** — inventory is temporarily reserved until a defined expiry.
- **Pending payment/guarantee** — guest action or payment confirmation is required.
- **Confirmed** — booking is accepted under the property's approved payment or guarantee policy.
- **Checked in** — the guest has arrived and the stay is active.
- **Checked out** — the stay is complete.
- **Cancelled** — booking was cancelled under the applicable policy.
- **No-show** — guest did not arrive and the booking was handled under the applicable policy.
- **Expired** — an unconfirmed hold passed its expiry time.

**Open:** Approve the duration of a hold and the policy for confirming bookings with unpaid balances.

### 5.3 Payment states

Payment status must be separate from reservation status. Recommended states:

- Unpaid
- Pending
- Partially paid
- Paid
- Failed
- Refund pending
- Partially refunded
- Refunded
- Reconciliation required

**Recommended control:** A guest uploading or presenting proof of EFT must not, by itself, mark the payment as verified. An authorized staff member or a validated payment integration must confirm settlement.

**Open:** Confirm which payment methods will be offered at launch, the deposit rules, cancellation/refund policy, and who may approve manual payment changes.

## 6. Functional requirements

### 6.1 Public website and booking

- **WEB-01:** Display property information, room types, facilities, location, contact details, policies, and approved promotional content.
- **WEB-02:** Support the approved public-facing languages. The supplied material identifies five languages; confirm the language list and who will supply translations.
- **WEB-03:** Allow visitors to enter stay dates, guest count, and any other approved search criteria.
- **WEB-04:** Show only availability and rates returned by the authoritative reservation/inventory process.
- **WEB-05:** Support the approved direct-booking flow, including guest details, policy acknowledgement, payment or guarantee steps, and confirmation.
- **WEB-06:** Show clear outcomes for successful, pending, failed, and expired booking attempts.
- **WEB-07:** Provide an accessible contact or enquiry path for guests who cannot complete an online booking.
- **WEB-08:** Allow authorized staff to manage public content without developer intervention, with preview and publication controls.

**Acceptance criteria**

- The system never confirms two overlapping reservations for the same physical room.
- A guest receives a clear result for each booking attempt, including next steps if payment is pending.
- Public availability reflects the configured inventory source and is not based on stale manually maintained data.
- Content editors can save a draft and preview it before publication.

### 6.2 Reception and reservation management

- **RES-01:** Staff can create, view, amend, cancel, and search reservations according to their permissions.
- **RES-02:** Staff can create bookings from approved sources, including direct online bookings, walk-ins, telephone, and email.
- **RES-03:** Record the booking source and any relevant external reference.
- **RES-04:** Show reservation state, payment state, stay dates, room type, assigned physical room, guest count, and relevant notes.
- **RES-05:** Support approved guest preferences and accessibility or service requirements, with appropriate access controls.
- **RES-06:** Provide a waitlist or enquiry list if the owner confirms that workflow is needed.
- **RES-07:** Provide a guest-history view only to staff who need it for their work and only for the approved retention period.
- **RES-08:** Keep a change history for date, room, guest-count, rate, and status changes.

**Acceptance criteria**

- Staff cannot save a room assignment that conflicts with another confirmed stay.
- Changes to a reservation show who made the change and when.
- A user without the required permission cannot view or modify restricted guest or financial information.
- Manual bookings follow the same availability and conflict checks as online bookings.

### 6.3 OTA and channel integration

- **INT-01:** Validate the selected PMS or channel manager's support for each required OTA before committing to an integration design.
- **INT-02:** Record the channel source and external reservation reference.
- **INT-03:** Define which system is authoritative for rates, room-type inventory, physical-room assignment, and reservation changes.
- **INT-04:** Detect and visibly flag failed or delayed synchronization.
- **INT-05:** Provide a documented manual recovery process when an external connection is unavailable.

**Recommendation:** Prefer an established PMS/channel manager integration over building separate custom connections to each OTA.

**Open:** Confirm the actual OTA services, expected volume, channel-manager choice, and whether physical room assignments need to be sent to external channels.

### 6.4 Payments, invoices, and reconciliation

- **PAY-01:** Record payment method, amount, currency, date, status, and related reservation.
- **PAY-02:** Support the payment methods approved by the owner. The source material mentions Instant EFT, ordinary EFT, and cards; confirm final launch scope and providers.
- **PAY-03:** Separate payment initiation from payment settlement confirmation.
- **PAY-04:** Allow authorized staff to record and reconcile manual EFT payments.
- **PAY-05:** Record payment exceptions, including failed, duplicated, late, partial, or unmatched payments.
- **PAY-06:** Generate invoices or receipts with the required property and transaction details.
- **PAY-07:** Restrict refunds, write-offs, and manual status overrides by role and log them.
- **PAY-08:** Do not store raw card numbers or security codes in the guesthouse platform. Use an appropriately validated payment provider and confirm applicable PCI responsibilities.

**Open:** Confirm invoice fields, tax requirements, deposit rules, cancellation fees, refund authority, and reconciliation responsibilities with the property's finance adviser.

### 6.5 Guest QR services

The room QR code should provide a secure route to guest services—not public access to the guest's identity or booking details.

- **GUEST-01:** Each room or stay must have a QR access method that can be disabled or replaced.
- **GUEST-02:** Verify access using a one-time code sent to the booking contact or another contact authorized by the verified booker.
- **GUEST-03:** Limit attempts and rate-limit code requests to reduce abuse.
- **GUEST-04:** Allow an authorized booker to designate additional stay contacts under the approved policy.
- **GUEST-05:** Show only the service options relevant to the stay and property.
- **GUEST-06:** Allow a guest to submit a request, see that it was received, and—if approved—see status updates.
- **GUEST-07:** Do not show one guest's request, name, or reservation information to another guest.
- **GUEST-08:** Automatically expire guest access at checkout or reservation cancellation.
- **GUEST-09:** Log staff access to sensitive guest-service information.

**Acceptance criteria**

- A person with only the QR image cannot view the guest's personal or payment information.
- A valid one-time code provides only the access permitted for the relevant stay.
- Expired, cancelled, or revoked access cannot create new requests.
- Staff can identify, assign, update, and close each request.

### 6.6 Guest requests and service orders

- **SRV-01:** Support the services approved by the property, such as room assistance, amenities, or food and beverage requests.
- **SRV-02:** Capture the request, time submitted, room or stay reference, status, assigned team, and completion time.
- **SRV-03:** Route requests to the appropriate staff queue.
- **SRV-04:** Allow authorized staff to acknowledge, assign, update, complete, or cancel a request.
- **SRV-05:** Preserve a timestamped history of status changes.
- **SRV-06:** If charges apply, show the charge and obtain any required guest confirmation before submitting the order.
- **SRV-07:** Provide an escalation path for requests that exceed the owner-approved response target.

**Open:** Confirm the service catalogue, operating hours, pricing, charge-to-room rules, and urgent-request categories.

### 6.7 Housekeeping and room readiness

- **HK-01:** Show rooms requiring cleaning, rooms in progress, rooms awaiting inspection, and rooms approved as ready.
- **HK-02:** Allow work to be assigned by room, date, staff member, and priority.
- **HK-03:** Support a room-specific cleaning checklist.
- **HK-04:** Record who completed the work and when.
- **HK-05:** Allow an authorized inspector to pass or fail an inspection and record any notes or evidence required by policy.
- **HK-06:** Return failed inspections to the appropriate work queue as rework.
- **HK-07:** Prevent a room from being marked ready unless the required cleaning and inspection steps are complete, unless an authorized override is recorded.
- **HK-08:** Make out-of-service or maintenance-blocked rooms visible to reception and reservation workflows.

**Recommended room workflow:**
Occupied → Checkout due → Cleaning required → Cleaning in progress → Inspection required → Ready
Additional states: Do not disturb, Maintenance hold, Out of service.

**Acceptance criteria**

- Reception can see the current readiness state and the last update time.
- A failed inspection generates a rework task and does not silently leave the room marked ready.
- A room placed on maintenance hold is excluded from bookable inventory according to the approved policy.

### 6.8 Maintenance and lost property

- **MAINT-01:** Allow authorized users to log a fault against a room, area, or asset.
- **MAINT-02:** Capture description, priority, reporting time, assigned person or team, status, and resolution.
- **MAINT-03:** Make urgent faults visible to the appropriate staff without exposing unnecessary guest information.
- **MAINT-04:** Allow work to be paused, reassigned, and closed with a resolution note.
- **LP-01:** Record lost property with found date, location, description, storage status, and release or disposal outcome.
- **LP-02:** Restrict access to lost-property records where they contain personal details.
- **LP-03:** Keep an auditable history of changes to lost-property records.

### 6.9 Staff attendance and rosters

- **STAFF-01:** Provide staff roster and attendance views appropriate to the approved roles.
- **STAFF-02:** Validate the proposed Hikvision device integration, including device model, supported API, network requirements, and vendor limitations.
- **STAFF-03:** Store only the attendance information required for the approved operational purpose.
- **STAFF-04:** Define an approved manual fallback process for outages or missed punches.
- **STAFF-05:** Restrict access to attendance records and record administrative corrections.
- **STAFF-06:** Confirm the proposed 12-month attendance-history period with the owner and relevant privacy or employment adviser.

**Open:** Confirm the exact device model, whether any biometric data is involved, integration support, fallback rules, and the legal basis and retention policy for attendance data.

### 6.10 Content management

- **CMS-01:** Allow authorized editors to create, preview, publish, and unpublish website content.
- **CMS-02:** Record the editor and publication time for content changes.
- **CMS-03:** Support the approved languages and indicate where translations are missing or out of date.
- **CMS-04:** Restrict administrative settings separately from routine content editing.
- **CMS-05:** Keep an approved process for urgent corrections to public information.

### 6.11 Reporting and alerts

- **REP-01:** Provide role-appropriate views of arrivals, departures, occupancy, room readiness, open service requests, and maintenance issues.
- **REP-02:** Provide approved financial reports based on reconciled or clearly labelled payment data.
- **REP-03:** Define each metric's formula and date basis, including occupancy, revenue, cancellations, and payment totals.
- **REP-04:** Support a daily operational snapshot at the owner-approved time. The source material proposes 17:00; confirm the timezone, recipients, and report contents.
- **REP-05:** Ensure reports distinguish confirmed, pending, cancelled, and no-show bookings.
- **REP-06:** Record report generation time and data range.
- **REP-07:** Restrict exports containing personal or financial data by role.

**Recommendation:** Use an in-app dashboard and a controlled email summary rather than sending detailed guest information through email or push notifications.

## 7. Recommended architecture and integration approach

The final product and provider choices remain Open, but the design should follow these principles:

- **One authoritative reservation and inventory source.** Avoid separate, independently maintained availability records in the website, reception system, and OTA tools.
- **Use established integrations where practical.** Assess PMS, channel-manager, payment, messaging, and attendance options before approving custom development.
- **Keep payments outsourced.** The platform should store transaction references and payment status, not sensitive card data.
- **Isolate external connections.** Integrations should report failures, retain references, and support a documented recovery process.
- **Plan for manual fallback.** Staff must know what to do when payment, OTA, messaging, or attendance connections are unavailable.
- **Confirm data ownership and portability.** Contracts should cover account ownership, exports, backups, API access, and termination assistance.
- **Use separate environments.** Production data should not be copied into design, test, or training systems without suitable safeguards.

### Required discovery before final vendor selection

- Demonstrate room-type and physical-room inventory behavior.
- Test direct booking and OTA synchronization.
- Confirm payment settlement and refund workflows.
- Validate the Hikvision model and supported integration method.
- Confirm messaging costs, delivery behavior, and required approvals.
- Review data export, backup, restoration, and account ownership.
- Compare total costs over three years, including licenses, transaction fees, maintenance, hosting, and training.

## 8. Data and privacy requirements

The system may process guest identity and contact information, booking details, payment references, staff attendance information, and service-request data.

- **PRIV-01:** Collect only information needed to operate the service.
- **PRIV-02:** Restrict access based on role and operational need.
- **PRIV-03:** Use encryption in transit and appropriate protection at rest.
- **PRIV-04:** Record access and changes to sensitive records.
- **PRIV-05:** Define approved retention periods and deletion or anonymization procedures.
- **PRIV-06:** Provide an approved process for correcting personal information and handling access or deletion requests.
- **PRIV-07:** Keep guest communications and consent preferences in a form staff can review.
- **PRIV-08:** Ensure opt-outs are respected by automated communication workflows.
- **PRIV-09:** Review contracts and data-handling responsibilities with relevant privacy and legal advisers.

**Recommendation:** Treat applicable South African privacy, consumer, payment, tax, and employment obligations as items for qualified local review. The system should support compliance workflows; it should not be treated as a substitute for legal advice.

## 9. Non-functional requirements

The figures below are recommended starting targets, not confirmed commitments. The owner and delivery team should approve or revise them before implementation.

| Area | Recommended target |
|---|---|
| Availability | 99.5% monthly availability for core booking and staff functions, excluding agreed maintenance. |
| Performance | Common staff screens should load within 2 seconds at the 95th percentile under agreed normal load. |
| Booking safety | Inventory changes must prevent duplicate confirmed assignments, including simultaneous booking attempts. |
| Recovery | Define a backup and restoration plan; recommended initial target is an RPO of 4 hours and RTO of 8 hours, subject to cost and provider capability. |
| Auditability | Sensitive changes and access events must include user and timestamp; retention period must be approved. |
| Accessibility | Public booking and guest service pages should be designed and tested for accessible keyboard and screen-reader use. |
| Compatibility | Support current major mobile and desktop browsers agreed during design. |
| Scalability | Support the property's expected room, staff, booking, and request volumes with room to grow. |
| Maintainability | Document configuration, integrations, deployment, backups, recovery, and operational handover. |
| Monitoring | Alert designated administrators to significant integration failures, repeated booking errors, and service outages. |

## 10. Core data objects

The solution design should account for at least these entities:

- Property
- Room type
- Physical room
- Rate plan
- Availability or inventory block
- Reservation
- Guest
- Stay contact or authorized guest
- Payment transaction
- Invoice or receipt
- Guest service request
- Service catalogue item
- Housekeeping task
- Room inspection
- Maintenance issue
- Lost-property record
- Staff user
- Role and permission
- Roster or attendance record
- Message or communication record
- Audit event
- External-channel reference

The final data model must distinguish reservation, stay, room, and payment. Combining these concepts would make room changes, cancellations, payment reconciliation, and guest verification harder to manage safely.

## 11. Global acceptance criteria

The implementation is not ready for operational handover until the following have been demonstrated:

- Direct, staff-created, and approved external bookings use the same inventory conflict controls.
- No two confirmed stays can overlap in the same physical room.
- Reservation and payment statuses are separate and accurately represented.
- Manual payment reconciliation and exception handling have been tested.
- Guest QR access cannot reveal another guest's information or persist beyond the authorized stay.
- Room cleaning, inspection, failure, and rework workflows are testable end to end.
- Role permissions and audit logs have been demonstrated for sensitive actions.
- Reports show their date range, calculation rules, and relevant status distinctions.
- Backup restoration and outage fallback procedures have been tested.
- Staff training, operational documentation, account ownership, and data-export procedures are complete.

## 12. Delivery plan

### Phase 0 — Decisions and validation

- Confirm policies and open items in Section 13.
- Select or shortlist PMS, channel manager, payment provider, messaging provider, and attendance integration.
- Run practical integration tests before finalizing the architecture.
- Confirm privacy, tax, employment, and payment obligations with qualified advisers.
- Approve service catalogue, room types, rate plans, cancellation rules, and reporting definitions.

### Phase 1 — Core reservations and operations

- Property website and content management.
- Reservation and room-inventory workflows.
- Reception console.
- Payment tracking and invoice/receipt workflows.
- Core roles, audit logging, operational dashboards, and reports.
- Housekeeping, inspection, and maintenance basics.

### Phase 2 — Guest services and external integrations

- Guest QR and one-time-code verification.
- Guest service requests and staff fulfilment.
- Validated OTA/channel-manager integration.
- Payment-provider integration and reconciliation improvements.
- Approved messaging workflows.

### Phase 3 — Optimization

- Attendance integration, if validated and approved.
- Advanced reporting and operational alerts.
- Additional guest services and approved loyalty or post-stay features.
- Workflow improvements based on staff and guest feedback.

**Recommendation:** Do not make the launch dependent on unvalidated integrations. Each phase should have a manual, controlled fallback where appropriate.

## 13. Decisions required from the owner

The following decisions should be recorded in a decision register before implementation estimates are finalized.

1. What are the exact room types, physical rooms, and rate plans?
2. Do guests book a room type only, or may they choose a specific physical room?
3. Which booking channels and OTAs are required at launch?
4. Which system will be authoritative for inventory and rates?
5. How long does an unconfirmed booking hold last?
6. What payment or guarantee is required to confirm a reservation?
7. What is the policy for late EFTs, partial payments, cancellations, refunds, and no-shows?
8. Which payment methods and providers are in scope at launch?
9. Which languages are required, and who supplies and approves translations?
10. Which guest services, food or beverage options, and charges are in scope?
11. Which requests are urgent, and what response targets apply?
12. What is the final QR and authorized-additional-contact policy?
13. What are the attendance-device model and integration capabilities?
14. What attendance data is retained, for how long, and who can access it?
15. What should the 17:00 operational snapshot contain, and who receives it?
16. What retention periods apply to guest, payment-reference, attendance, and audit data?
17. What are the approved availability, performance, backup, and recovery targets?
18. Who owns vendor accounts, domains, data, integrations, and source/configuration materials?

## 14. Recommendation

Use this document as the baseline for solution design and vendor evaluation. Before approving a fixed scope or price, resolve the owner decisions above, validate the critical integrations, and add signed-off acceptance criteria to the chosen implementation plan. The most important early validation is the inventory and OTA workflow, followed by payment reconciliation and guest QR security, because errors in these areas can directly affect bookings, revenue, and guest trust.
