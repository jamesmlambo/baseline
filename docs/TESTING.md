# Testing

- Framework: **Pest 4** on PHPUnit 12.
- Database: **PostgreSQL** `baselineCL_testing`, never SQLite, so constraints, exclusion rules and locking behave as in production.
- Feature tests use `RefreshDatabase` and seed staff roles automatically (`tests/Pest.php`).
- Helper: `staffUser(StaffRole::Owner)` creates a user with the given roles.

## Commands

```powershell
php artisan test --compact
php artisan test --compact --filter=Availability
php vendor/bin/pint
npm run build
```

## Conventions
- Name tests after behaviour and, where possible, the requirement ID, e.g. `it('BOOK-04 releases expired holds')`.
- Concurrency tests open **separate database connections** to prove that only one of two simultaneous bookings for the last room succeeds (INV-03, INV-04).
- Never fake a pass. If a test can't run (e.g. it needs a real device), mark it skipped with the reason.

## Results log

| Date | Milestone | Tests | Result |
|---|---|---|---|
| 2026-11-20 | M1 | 31 | All passed |
| 2026-11-21 | M2 | 81 | All passed (incl. 2 two-connection concurrency tests) |
| 2026-11-21 | M3 | 144 | All passed. Manual check of homepage and availability page at desktop and 375 px. |
| 2026-11-21 | M4 | 186 | All passed. Manual end-to-end booking + fake Instant EFT payment in the browser. |
| 2026-11-21 | M5 | 215 | All passed. Admin reception screens covered by Livewire tests; not yet clicked through by a person (needs 2FA sign-in). |
| 2026-11-21 | M6 | 244 | All passed. Memory limit for tests raised to 512 MB. WhatsApp price-list page checked in the browser. |
| 2026-11-21 | M7 | 289 | All passed. Portal pages checked in the browser at 375 px; admin portal screens covered by Livewire tests. |
| 2026-11-21 | M8 | 322 | All passed. New admin and staff screens covered by Livewire tests only (sign-in needed for a manual check). |
| 2026-11-21 | M9 | 364 | All passed. New HR and staff screens covered by Livewire tests only. |
| 2026-11-21 | M10 | 392 | All passed. Portal Book page checked in the browser at 375 px; admin and kitchen screens by Livewire tests. |
| 2026-11-21 | M11 | 410 | All passed. Owner dashboard and report archive by Livewire tests (owner sign-in needs 2FA). |
| 2026-11-21 | M12 | 425 | All passed. Channel import, iCal and monitoring by feature tests with a fake connected driver; no real provider. |
| 2026-11-21 | M13 | 433 | Privacy, security, readiness tests added; accessibility checked in the browser; backup restore test passed. |
| 2026-11-21 | Final verification | 439 | All passed (parallel). Pint, build, composer audit, npm audit clean. Fixes: role escalation, contact encryption, walk-in double payment, log masking. |

## Global acceptance criteria (FINAL_SPECIFICATION §23)

| # | Criterion | Demonstrated by |
|---|---|---|
| 1 | Every source uses the same inventory checks; no overlapping assignments, even concurrently | `tests/Concurrency/LastRoomRaceTest` (two real connections, "INV-03 lets exactly one of two bookings for the last room succeed"), "INV-04 lets the database refuse overlapping active assignments", `ChannelsTest` "DEC-02 never overbooks", `ChannelsTest` iCal clash test |
| 2 | Booking, payment and room-readiness statuses separate and correct | `BOOK-03` state-machine tests, `PAY-07` tests, `HousekeepingTest` HK-06 tests |
| 3 | EFT verification, duplicate/out-of-order webhooks, late payments, dual-approval refunds | "PAY-07 never confirms a booking on proof of EFT alone", "PAY-06 processes a duplicated webhook only once", "PAY-06 ignores a failure that arrives after the success", "PEND-02 flags money that arrives after the hold expired, then reinstates if the room is free" (all `PaymentsTest`), "DEC-06 needs a second, different person", "DEC-06 is also enforced by the database" |
| 4 | QR alone reveals nothing; portal ends at checkout/reassignment; OTP limits | `PortalAccessTest` GST-02 (anti-enumeration, 5 tries, 3 sends/15 min, expiry, device rate limit) and GST-06 (checkout, room move, QR replaced, revoke) |
| 5 | Clean → inspect → fail → rework → re-inspect → Ready | "HK-05 sends failed items back to the same worker as rework and inspects again", "HK-06 sends a finished checkout clean to inspection, and a pass makes the room ready" |
| 6 | Off-duty never absent; duplicate attendance imports have no effect | "EMP-04 never marks off-duty staff absent", "ATT-02 imports a CSV once: importing it again adds nothing" |
| 7 | Role permissions and audit logs for sensitive actions | Policy tests in every milestone; `PrivacyAndSecurityTest` "SEC-02 refuses a housekeeping worker on a payment screen and logs the attempt"; audit assertions on refunds, corrections (ATT-04), overrides (HK-06), anonymisation |
| 8 | 17:00 report generates, delivers, logs and retries | `ReportsTest` REP-03 tests (once per day, email + push log, retries with back-off, no guest data in email) |
| 9 | Backup restore tested and documented | `php artisan backup:restore-test` — passed 21 Nov 2026 (docs/BACKUP_RESTORE.md results log) |
| 10 | No demo content/accounts/fake drivers in production; PENDING flagged | `php artisan app:production-check`; "ACC-10 blocks go-live while demo data, demo accounts or fake drivers are active" |

Run time: about 5–10 minutes one at a time, or about **3 minutes in parallel**: `php artisan test --parallel --processes=4` (paratest comes with Pest; 4 workers were as fast as 12 on the development laptop). Each worker gets its own copy of the test database (`baselineCL_testing_test_1`, `_2`, …), created and reused automatically. Never point these at the live or another project's database.
