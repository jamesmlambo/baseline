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
| SEC-08 | Upload checks, EXIF stripped | Tested (MIME + size + EXIF; antivirus interface pending) | `StripImageMetadata` | `RedirectAndMediaTest` |
