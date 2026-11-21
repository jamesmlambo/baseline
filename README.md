# Baseline Lodge

Guesthouse website and management platform: public website, bookings, reception, guest portal, housekeeping, staff and owner reporting. Built with Laravel 13, Filament 5 and PostgreSQL.

- Specification: [`docs/FINAL_SPECIFICATION.md`](docs/FINAL_SPECIFICATION.md) (client source: `docs/SPEC_v5.3.pdf`)
- Build instructions: [`docs/MASTER_PROMPT.md`](docs/MASTER_PROMPT.md)
- Progress: [`docs/IMPLEMENTATION_PROGRESS.md`](docs/IMPLEMENTATION_PROGRESS.md)

## Requirements

PHP 8.4+ (with `pdo_pgsql`, `pgsql`, `intl`, `gd`, `zip`, `bcmath`, `sodium`), Composer 2, Node.js LTS, PostgreSQL 16+.

## Local installation (Windows, PowerShell)

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
```

### PostgreSQL

Create the two databases. The quotes matter, because the names are mixed-case:

```powershell
psql -U postgres -f database/create-databases.sql
```

Set `DB_USERNAME` / `DB_PASSWORD` in `.env` if your server needs them. Tests use `baselineCL_testing` (see `phpunit.xml`) and **must** run on PostgreSQL.

### Migrations and seed data

```powershell
php artisan migrate --seed
```

- `RoleSeeder`, `SettingSeeder`, `WebsiteContentSeeder`, `MessageTemplateSeeder`, `TaskChecklistSeeder` and `ChannelSeeder` are safe in every environment and never overwrite edits.
- In the `local` environment only, demo seeders add DEMO rooms, website content, menu, staff, shifts and leisure services, all marked DEMO. `DevelopmentUserSeeder` creates one demo account per staff role with random passwords, written to `storage/app/private/dev-credentials.txt` (never committed). **Re-running it resets those passwords.** Every demo seeder refuses to run outside `local`.

## Running

In VS Code, run the **Dev: all** task (Terminal › Run Task), or start each part yourself:

```powershell
php artisan serve --port=8001
npm run dev
php artisan queue:listen --tries=3
php artisan schedule:work
```

| Area | URL | Who |
|---|---|---|
| Public website | http://127.0.0.1:8001 | Everyone |
| Administration | http://127.0.0.1:8001/admin | Administrator, Owner, Manager, Receptionist, Content editor. Two-factor login is required. |
| Staff portal | http://127.0.0.1:8001/staff | All staff roles: my tasks, inspections, stock, kitchen, my shifts, leave, attendance, details |
| Guest portal | http://127.0.0.1:8001/stay | Checked-in guests, through the room QR + SMS code or the signed link sent at check-in |

On first sign-in to `/admin` you will be asked to set up an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, etc.) and to save the recovery codes.

The **queue worker** and the **scheduler** must run for checkout turnover tasks, messages, hold expiry, guest escalations, the 17:00 report, attendance, channel syncs and backups.

## Useful commands

| Command | What it does |
|---|---|
| `php artisan app:production-check` | What still blocks going live (demo data/accounts, fake drivers, pending decisions, backups) |
| `php artisan backup:run` / `backup:restore-test` | Make a backup / prove the newest one restores (scratch database, dropped afterwards) |
| `php artisan privacy:retention` | Show what the retention rules would change (nothing changes until approved, PEND-11) |
| `php artisan reports:daily` | Generate and send today's owner report if it's due |
| `php artisan attendance:derive --date=YYYY-MM-DD` | Recalculate attendance for a day |
| `php artisan channels:sync` | Sync enabled channels (logged as "not connected" until a provider is connected) |

## Integrations

Payments, SMS, WhatsApp, web push, virus scanning, channel managers and the Hikvision attendance device all run on fake/log/null drivers until a provider is approved. See [`docs/INTEGRATIONS.md`](docs/INTEGRATIONS.md).

## Tests and code style

```powershell
php artisan test --compact
php vendor/bin/pint
npm run build
```

See [`docs/TESTING.md`](docs/TESTING.md).

## Backup and restore

`spatie/laravel-backup`: database every 4 hours, database + uploads nightly, encrypted with `BACKUP_ARCHIVE_PASSWORD`. Set `DB_DUMP_BINARY_PATH` if `pg_dump` isn't on the PATH (Herd: `C:/Users/<you>/.config/herd/bin/services/postgresql/18/bin/`). Full procedure and restore log: [`docs/BACKUP_RESTORE.md`](docs/BACKUP_RESTORE.md).

## Going live

[`docs/PRODUCTION_CHECKLIST.md`](docs/PRODUCTION_CHECKLIST.md) and `php artisan app:production-check`.

## Documents

[`ARCHITECTURE`](docs/ARCHITECTURE.md) · [`REQUIREMENTS_TRACEABILITY`](docs/REQUIREMENTS_TRACEABILITY.md) · [`PENDING_DECISIONS`](docs/PENDING_DECISIONS.md) · [`INTEGRATIONS`](docs/INTEGRATIONS.md) · [`COMPLIANCE_TODO`](docs/COMPLIANCE_TODO.md) · [`IMPLEMENTATION_PROGRESS`](docs/IMPLEMENTATION_PROGRESS.md) · [`TESTING`](docs/TESTING.md) · [`HIKVISION_POC`](docs/HIKVISION_POC.md) · [`BACKUP_RESTORE`](docs/BACKUP_RESTORE.md) · [`REPORT_METRICS`](docs/REPORT_METRICS.md) · [`PRODUCTION_CHECKLIST`](docs/PRODUCTION_CHECKLIST.md)
