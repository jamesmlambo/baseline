# Architecture decisions

Record every compatibility or design decision here (MASTER_PROMPT §0).

| Date | Decision | Why |
|---|---|---|
| 2026-11-20 | Database is `baselineCL` / `baselineCL_testing`, not `baseline_lodge` | `baseline_lodge` belongs to another Laravel project (user instruction). |
| 2026-11-20 | Running on PHP 8.5 (Herd) instead of the 8.4 target | Every installed package resolved on 8.5 without conflicts. |
| 2026-11-20 | Filament 5.11 for `/admin` and `/staff` | Latest stable release that supports Laravel 13. |
| 2026-11-20 | **Filament's built-in multi-factor auth instead of Laravel Fortify** | Only staff sign in, and only through the Filament panels. Filament provides TOTP + recovery codes, login throttling and password reset, so Fortify would duplicate them. Fortify was removed. |
| 2026-11-20 | 2FA required for **every** `/admin` user (including Content editor) | Filament evaluates the "required" flag when routes are registered, before a user exists, so it can't vary by role. This is stricter than SEC-03, never looser. `/staff` keeps 2FA optional. |
| 2026-11-20 | Roles are a PHP enum (`StaffRole`) backed by spatie/laravel-permission roles | Type-safe role checks in code, with DB-backed assignment. Finer permissions will be added per module. |
| 2026-11-20 | Role changes go through `syncRoles()` and permission events (`events_enabled = true`) | So every role change reaches the audit log with who made it. |
| 2026-11-20 | Pest 4 on PHPUnit 12, against PostgreSQL | MASTER_PROMPT requires PostgreSQL for tests so constraints behave as in production. |
| 2026-11-20 | Settings are a key/value table with `is_pending_client_decision` + `decision_reference` | Makes PEND items in FINAL_SPECIFICATION §3.2 visible and auditable in admin. |
| 2026-11-20 | App timezone `Africa/Johannesburg` | Business times are SAST. Timestamp columns use `timestampTz`. |
