# Implementation progress

New session? Read `docs/MASTER_PROMPT.md`, `docs/FINAL_SPECIFICATION.md` and this file, then continue from **Next**.

## Current status

- **Milestone 1 — Foundation:** mostly done (see "Still to do in M1").
- **Next:** finish the M1 leftovers, then **Milestone 2 — Rooms & booking engine**.

## Environment

| Item | Value |
|---|---|
| Laravel | 13.43 |
| PHP | 8.5.12 (Herd) |
| PostgreSQL | 18.4, user `postgres`, local trust auth (no password) |
| App database | `baselineCL` |
| Test database | `baselineCL_testing` (used by `phpunit.xml`) |
| Not ours | `baseline_lodge`, `baseline_lodge_test`, `baseline_lodge_testing` belong to another Laravel project. **Do not touch them.** |
| Local URL | http://127.0.0.1:8000 (`php artisan serve`) |

## Milestone 1 — Foundation (20 Nov 2026)

### Done
- Laravel Boost installed (`CLAUDE.md`, `.mcp.json`, `boost.json`, `.claude/skills`).
- Switched to PostgreSQL: `.env`, `.env.example` and `phpunit.xml`. Timezone Africa/Johannesburg, locale en_ZA, app name "Baseline Lodge".
- Packages: Filament 5.11, spatie/laravel-permission 8.5, spatie/laravel-activitylog 5.1, Pest 4.7 + Laravel plugin.
- **Panels:** `/admin` and `/staff`, both with login, password reset and profile. App-based TOTP two-factor login with recovery codes. 2FA is **required** on `/admin` and optional on `/staff`.
- **Roles** (`App\Enums\StaffRole`): Administrator, Owner, Manager, Receptionist, Housekeeping worker, Maintenance worker, Inspector, Content editor. `/admin` allows Administrator, Owner, Manager, Receptionist and Content editor. `/staff` allows any role. Deactivated accounts are blocked everywhere.
- **Audit log:** user field changes, setting changes, and role assigned/removed (who did it and which roles). `last_login_at` is recorded on each sign-in.
- **Staff accounts** (`Admin › People`): Administrator and Owner only. No deletion, only deactivation. An Administrator cannot edit an Owner's account.
- **System settings** (`Admin › Settings`): seeded key/value settings. Items awaiting a client decision show a warning and a PEND reference, and the navigation badge counts them. Settings cannot be created or deleted from the UI.
- **Seeders:** `RoleSeeder` and `SettingSeeder` (both safe to re-run). `DevelopmentUserSeeder` is local-only: one demo account per role with random passwords written to `storage/app/private/dev-credentials.txt`, which is not committed.
- **Public site:** base layout (skip link, DEMO banner outside production, official communication notice, staff sign-in link) and a placeholder homepage. Fonts are self-hosted through the Vite fonts plugin (Fraunces + Instrument Sans).
- **Tests:** 31 passing on PostgreSQL. They cover panel access per role, 2FA enforcement, encrypted 2FA secret, staff management policies, role-change audit, settings seeding without invented values, and the homepage.

### Still to do in M1
- First Git commit. The repository is initialised, but Git has no user name/email set yet.
- `.vscode/` tasks and recommended extensions (MASTER_PROMPT §0A). Note: `/.vscode` is currently in `.gitignore`.
- `README.md` rewrite, `docs/REQUIREMENTS_TRACEABILITY.md`, `docs/INTEGRATIONS.md`, `docs/COMPLIANCE_TODO.md`, `docs/TESTING.md`.

### How to run
```powershell
php artisan migrate --seed
php artisan serve
php artisan test --compact
```
Demo staff logins: `storage/app/private/dev-credentials.txt`. On first sign-in to `/admin`, set up an authenticator app.
