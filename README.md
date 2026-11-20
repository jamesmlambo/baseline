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

- `RoleSeeder` and `SettingSeeder` are safe in every environment and never overwrite changed settings.
- In the `local` environment, `DevelopmentUserSeeder` creates one demo account per staff role with random passwords, written to `storage/app/private/dev-credentials.txt`. That file is never committed. The seeder refuses to run outside `local`.

## Running

In VS Code, run the **Dev: all** task (Terminal › Run Task), or start each part yourself:

```powershell
php artisan serve
npm run dev
php artisan queue:listen --tries=3
php artisan schedule:work
```

| Area | URL | Who |
|---|---|---|
| Public website | http://127.0.0.1:8000 | Everyone |
| Administration | http://127.0.0.1:8000/admin | Administrator, Owner, Manager, Receptionist, Content editor. Two-factor login is required. |
| Staff portal | http://127.0.0.1:8000/staff | All staff roles |
| Guest portal | `/stay` | Coming in Milestone 7 |

On first sign-in to `/admin` you will be asked to set up an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, etc.) and to save the recovery codes.

## Integrations

Payments, SMS, WhatsApp, channel managers and the Hikvision attendance device all run on fake/log drivers until a provider is approved. See [`docs/INTEGRATIONS.md`](docs/INTEGRATIONS.md).

## Tests and code style

```powershell
php artisan test --compact
php vendor/bin/pint
npm run build
```

See [`docs/TESTING.md`](docs/TESTING.md).

## Backup and restore

Planned with `spatie/laravel-backup` in Milestone 13. Until then, use `pg_dump`:

```powershell
pg_dump -U postgres -Fc -f baselineCL.dump baselineCL
```
