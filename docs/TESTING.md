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
