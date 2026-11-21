# Requirements traceability

IDs come from `FINAL_SPECIFICATION.md`. Status values: Approved · Pending client decision · Recommended · Requires external verification · Implemented · Tested · Blocked.

Only requirements that have been worked on are listed. Everything else is still Approved/Pending and not started.

| ID | Requirement (short) | Status | Where | Tests |
|---|---|---|---|---|
| DEC-01 | Name is Baseline Lodge | Tested | `.env`, layout, panels | `ExampleTest` |
| SEC-01 | Individual accounts | Implemented | Staff accounts screen | — |
| SEC-02 | Server-side policies | Tested | `UserPolicy`, `SettingPolicy` | `StaffManagementTest` |
| SEC-03 | 2FA for privileged roles | Tested (stricter: all `/admin` users) | `AdminPanelProvider` | `PanelAccessTest` |
| SEC-04 | Login throttling, reset, sessions | Implemented (Filament) | Panels | `PanelAccessTest` (login pages) |
| SEC-05 | Audit log | Tested (users, roles, settings) | `User`, `Setting`, `AppServiceProvider` | `StaffManagementTest` |
| SEC-06 | Multiple roles, audited changes | Tested | `StaffRole`, `syncRoles` | `StaffManagementTest` |
| SEC-07 | Encrypt sensitive fields | Tested (2FA secret, recovery codes) | `User` casts | `PanelAccessTest` |
| CMS-06 | Site settings for Admin/Owner only | Tested | `SettingPolicy` | `StaffManagementTest` |
| WEB-08 | No invented facts / DEMO badge | Tested | Layout, `SettingSeeder` | `ExampleTest`, `StaffManagementTest` |
| PEND-01–14 | Pending decisions visible in admin | Implemented | `SettingSeeder`, Settings screen | `StaffManagementTest` |
| DEC-02 / INV-02 | Physical room assigned at booking | Tested | `PlaceBooking` | `PlaceBookingTest` |
| INV-01 | Types, rooms, seasonal rates, blocks | Tested (rate plans, coupons, taxes deferred to M4) | Models, admin screens | `InventoryTest`, `InventoryAdminTest` |
| INV-03 | One active claim per room night | Tested | `room_nights_one_active_claim` | `InventoryTest`, `LastRoomRaceTest` |
| INV-04 | Exclusion constraint on assignments | Tested | `booking_rooms_no_overlap` | `InventoryTest` |
| INV-05 | Maintenance / owner blocks | Tested | `BlockRoom`, Room blocks screen | `InventoryTest`, `InventoryAdminTest` |
| INV-06 | Re-check inside the transaction | Tested | `PlaceBooking` savepoint retry | `LastRoomRaceTest` |
| INV-07 | Audited reassignment with reason | Tested (portal/housekeeping hooks in M7/M8) | `ReassignRoom` | `InventoryTest` |
| BOOK-01 | One engine for all sources | Implemented (callers arrive in M4–M6, M12) | `PlaceBooking`, `BookingSource` | `PlaceBookingTest` |
| BOOK-02 | Multi-room bookings | Tested (groups, extras, coupons in M4) | `BookingRequest` | `PlaceBookingTest` |
| BOOK-03 | Valid transitions + history | Tested | `BookingStateMachine` | `BookingStateMachineTest` |
| BOOK-04 | Every hold expires; released every minute | Tested | `ReleaseExpiredHolds`, scheduler, DB CHECK | `BookingStateMachineTest` |
| BOOK-05 | Source + external reference recorded | Implemented | `bookings` columns | — |
| WEB-06 | Prices from the rate engine | Tested | `RateCalculator`, public room pages | `InventoryTest`, `PublicSiteTest` |
| §5.1 | All 20 public pages | Tested | `routes/web.php`, `resources/views/site` | `PublicSiteTest` |
| WEB-01 | Mobile-first, configurable branding | Implemented (brand tokens in `app.css`; admin-editable branding pending client assets) | Layout | Manual 375 px check |
| WEB-02 | WCAG 2.1 AA, Lighthouse ≥ 90 | Partly (semantic markup, labels, focus, skip link). Full audit in M13. | Layout, forms | — |
| WEB-03 | Responsive images | Implemented | `HasWebImages` conversions + srcset | — |
| WEB-04 | SEO, OG, sitemap, structured data | Tested | Layout, `SitemapController` | `PublicSiteTest` |
| WEB-05 | Old-URL redirects | Tested | `RedirectController`, Redirects screen | `RedirectAndMediaTest` |
| WEB-07 | Grading only with evidence | Tested | `SiteContent::gradingStars` | `PublicSiteTest` |
| WEB-08 | No invented facts, DEMO badges | Tested | Seeders, badges | `PublicSiteTest` |
| WEB-09 | Clear result screens | Partly (request sent / validation / nothing free). Payment results in M4. | Views | `EnquiryTest` |
| WEB-10 | Enquiry fallback form + WhatsApp | Tested | `ContactController`, `BookingEnquiryController` | `EnquiryTest` |
| CMS-01–05 | Pages, news workflow, preview, homepage posts | Tested (preview = "View" link on published pages; draft preview in M13) | Website screens, `PostWorkflow` | `WebsiteAdminTest`, `PublicSiteTest` |
| OBK-03 | Terms required, marketing consent separate | Tested | `/book`, contact form | `EnquiryTest` |
| BOOK-06 | Returning guests reuse their profile | Tested | `CreateReservation`, `Guest` hashes | `OnlineBookingTest` |
| SEC-07 | Encrypted guest contact details | Tested | `Guest` casts + lookup hashes | `OnlineBookingTest`, `FinanceAdminTest` |
| OBK-01/02 | Price summary; Instant EFT first | Tested | `/book`, `PaymentMethod::onlineOrder` | `OnlineBookingTest` |
| OBK-04 | Proof of EFT never "paid" | Tested | `PaymentLedger` | `PaymentsTest` |
| PAY-01 | Separate document types | Tested | Finance models | `PaymentsTest` |
| PAY-02 | Sequential numbering, PDFs | Tested | `DocumentNumberer`, `DocumentPdf` | `PaymentsTest`, `OnlineBookingTest` |
| PAY-03 | Issued documents immutable | Tested | `IsIssuedDocument` | `PaymentsTest` |
| PAY-04 | Quotation → invoice | Tested | `DocumentIssuer::convertQuotation` | `PaymentsTest` |
| PAY-05 | Gateway interface + fake driver | Tested (real provider pending PEND-07) | `PaymentGateway`, `FakeGateway` | `OnlineBookingTest` |
| PAY-06 | Signatures, duplicates, out-of-order | Tested | `WebhookProcessor` | `PaymentsTest` |
| PAY-07 | Manual EFT verified by authorised staff | Tested | `VerifyEftPayment` | `PaymentsTest`, `FinanceAdminTest` |
| PAY-08 / DEC-06 | Dual-approval refunds | Tested (code + DB) | `RefundWorkflow`, CHECK | `PaymentsTest`, `FinanceAdminTest` |
| PAY-09 | No card data stored | Implemented (hosted page only) | Gateway design | — |
| PAY-10 | Exceptions queue, reconciliation | Implemented (queue); CSV export in M11 | Payments screen | `PaymentsTest` |
| PEND-02 | Late EFT path | Tested | `ReinstateBooking`, exception flag | `PaymentsTest` |
| PEND-06 | Owner-only overrides | Tested | `BookingPolicy::confirmWithoutPayment` | `FinanceAdminTest` |
| WA-04/05 | Unpaid invoice, confirm on verified payment | Tested (WhatsApp sending in M6) | Mails, `PaymentLedger` | `OnlineBookingTest` |
| REC-01 | Reception dashboard | Tested | `ReceptionStats`, arrivals/departures/in-house widgets | `ReceptionScreensTest` |
| REC-02 | Room-by-date calendar | Tested | `RoomCalendar`, Room calendar page | `ReceptionTest`, `ReceptionScreensTest` |
| REC-03 | Bookings, check-in/out, extend, move, charges, payments | Tested (mid-stay room move later) | `CheckIn`, `CheckOut`, `ExtendStay`, `AddCharge`, `ReassignRoom` | `ReceptionTest`, `ReceptionScreensTest`, `FinanceAdminTest` |
| REC-04 | Guest register, configurable fields | Tested (required fields VERIFY) | `GuestRegisterEntry`, `register.required_fields` | `ReceptionTest` |
| REC-05 | Key, items, complaints log | Tested | `FrontDeskLog`, front-desk log tab | `ReceptionTest`, `ReceptionScreensTest` |
| REC-06/07 | Document QR scanner, opaque revocable codes | Tested | `ResolveReceptionCode`, Scan document page, PDF QR | `ReceptionTest`, `ReceptionScreensTest` |
| OFF-01..03 | Offline read-only contingency | Implemented as printed sheet (OFF-03) | Arrivals sheet page | `ReceptionScreensTest` |
| MSG-01 | Provider interfaces, log drivers | Tested | `MessageProvider`, `LogProvider` | `MessengerTest` |
| MSG-02 | Templates, versions, idempotency | Tested | `MessageTemplate`, `Messenger` | `MessengerTest`, `PriceListTest` |
| MSG-03 | Automations | Tested (staff/owner notices M8/M11) | `BookingMessages`, `RunMessageAutomations` | `AutomationsTest`, `PriceListTest` |
| MSG-04 | Consent and opt-outs | Tested (inbound STOP needs a provider) | `Messenger::decide`, guest opt-outs | `MessengerTest` |
| MSG-05 | Status, retries, resend | Tested | `SendMessage` job, Resend action | `MessengerTest` |
| MSG-06 | Monthly cap, 80% alert, critical bypass | Tested | `SpendGuard` | `MessengerTest` |
| WA-01..03 | Secure expiring price lists, live prices, no hold | Tested | `CreatePriceList`, `/p/{token}` | `PriceListTest` |
| WA-06 | Document links by WhatsApp, PDFs by email | Tested | Booking messages + mails | `AutomationsTest` |
| CHK-03 | Farewell once, room Dirty, one checkout clean | Tested | `CheckOut`, `CreateTurnoverTasks` | `AutomationsTest`, `HousekeepingTest` |
| CHK-01/02 | Checkout balance gate, owner override, portal checkout | Tested | `CheckOut::byGuest`, portal Bill page | `ReceptionTest`, `PortalStayTest` |
| SEC-08 | Upload checks, EXIF stripped | Tested (MIME + size + EXIF; antivirus interface pending) | `StripImageMetadata` | `RedirectAndMediaTest` |
| GST-01 | Room QR identifies the room, replaceable | Tested | `Room::portalUrl`, `PortalAccess::rotateRoomQr`, Rooms › Room QR | `PortalAccessTest`, `PortalStaffScreensTest` |
| GST-02 | SMS code, anti-enumeration, expiry, attempts, send limits | Tested | `PortalAccess::requestCode/verifyCode`, rate limiters | `PortalAccessTest` |
| GST-03 | Code unreadable by staff; reception link after ID check | Tested | `PortalAccess::sendCode`, `receptionLink` | `PortalAccessTest`, `PortalStaffScreensTest` |
| GST-04 | Booker adds/removes other guests' phones | Tested | Portal › Other guests | `PortalAccessTest` |
| GST-05 | Signed portal link in welcome message | Tested | `PortalAccess::linkFor`, `portal_welcome` template | `PortalAccessTest` |
| GST-06 | Session scoped to stay; ends on checkout, move, QR change, revoke | Tested | `PortalAccess::currentStay`, `EnsurePortalStay` | `PortalAccessTest` |
| GST-07 | Requests, room service, messages, bill, reviews | Tested | `GuestRequests`, `ModerateReview`, portal pages | `PortalStayTest`, `PortalStaffScreensTest` |
| GST-08 | Reception inbox, alert, escalation | Tested | Guest requests/messages resources, `GuestPortalAlert`, `EscalateGuestRequests` | `PortalStayTest`, `PortalStaffScreensTest` |
| CHK-04 | Turnover idempotent on retry | Tested | `CreateTurnoverTasks`, `TaskManager::createOnce` | `HousekeepingTest` |
| HK-01 | Task types | Implemented | `TaskType` | `HousekeepingTest` |
| HK-02 | Assignee, checklist, evidence, photos | Tested | `TaskManager::complete`, My tasks | `HousekeepingTest`, `HousekeepingScreensTest` |
| HK-03 | Reception assigns housekeeping; owner/manager assign maintenance | Tested | `TaskPolicy::assignType` | `HousekeepingTest`, `HousekeepingScreensTest` |
| HK-04 | Monday deep clean with spec checklist | Tested | `GenerateDeepCleans`, `TaskChecklistSeeder` | `HousekeepingTest` |
| HK-05 | Scored inspections, rework, re-inspection | Tested (scale PENDING PEND-15) | `Inspections::record` | `HousekeepingTest`, `HousekeepingScreensTest` |
| HK-06 | Ready only after clean + inspection, owner override | Tested | `Inspections`, Room status board | `HousekeepingTest`, `HousekeepingScreensTest` |
| HK-07 | Cleaning effort per room type | Implemented (used by rosters in M9) | `CleaningEstimate` | — |
| HK-08 | Faults, preventive schedules, equipment history, warranties, costs | Tested | `TaskManager::reportFault`, `RunMaintenanceSchedules`, Equipment | `HousekeepingTest`, `HousekeepingScreensTest` |
| HK-09 | Stock ledger, low-stock alerts | Tested | `StockLedger` | `HousekeepingTest`, `HousekeepingScreensTest` |
| HK-10 | Restricted lost-property log | Tested | `LostPropertyItemPolicy` | `HousekeepingTest` |
