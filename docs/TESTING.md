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
