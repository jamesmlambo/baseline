# Backups and restore (PRIV-05, acceptance criterion 9)

Package: `spatie/laravel-backup` (v10). Config: `config/backup.php`. Targets (PEND-13): **RPO 4 hours** for the database, **RTO 8 hours**, a daily off-site copy.

## What is backed up

| What | When | How |
|---|---|---|
| Database (`pgsql`) | Every 4 hours (`backup:run --only-db`) | `pg_dump`, inside an encrypted zip |
| Database + uploaded files (`storage/app/private`, `storage/app/public`) | Nightly 01:30 | Same zip |
| Clean-up of old backups | Daily 03:00 | `backup:clean` (default strategy; oldest removed above 5 GB) |
| Health check | Daily 07:00 | `backup:monitor` — warns by email if the newest backup is over a day old or storage is too big |

The code is **not** in the backup: it lives in git. `.env` is **not** in the backup either — keep a copy of it (especially `APP_KEY` and `BACKUP_ARCHIVE_PASSWORD`) in a password manager. **Without `APP_KEY`, encrypted guest/staff fields can't be read; without `BACKUP_ARCHIVE_PASSWORD`, the backup can't be opened.**

## Settings (`.env`)

```
BACKUP_NAME=baseline-lodge
BACKUP_DISKS=backups,s3            # production: always add an off-site disk
BACKUP_ARCHIVE_PASSWORD=...         # AES-256 encryption of every zip
BACKUP_NOTIFY_EMAIL=owner@...       # failures and health warnings
DB_DUMP_BINARY_PATH=                # folder with pg_dump/psql if not on the PATH
```

`backups` is a local disk (`storage/app/backups`, never served). The off-site disk (e.g. an S3 bucket in another region, or the hosting provider's object storage) is added in `config/filesystems.php` when hosting is chosen; it needs the AWS/S3 Flysystem adapter package at that point.

## Restore test (do this monthly and after any server change)

```
php artisan backup:run --only-db
php artisan backup:restore-test
```

`backup:restore-test` unzips the newest backup with the archive password, loads the dump into a **separate scratch database** (`<DB_DATABASE>_restore_test`), compares every table's row count with the live database, then drops the scratch database. It refuses any name that doesn't end in `_restore_test`, so it can't touch a real database. `--keep` leaves the scratch database for inspection.

### Results log

| Date | Backup | Result | By |
|---|---|---|---|
| 21 Nov 2026 | `2026-11-21` database backup (local dev, PostgreSQL 18) | **Passed** — 90 tables restored, all row counts identical; scratch database removed | Development (Claude Code) |

## Full restore after a disaster

1. Provision the server (README "Production"), deploy the code from git, restore `.env` from the password manager.
2. Copy the newest zip from the off-site disk to the server.
3. Unzip it with the archive password (any AES-capable unzip tool, e.g. 7-Zip: `7z x backup.zip`). Inside: `db-dumps/postgresql-<database>.sql` and the `private/` and `public/` upload folders.
4. Create an empty database and load the dump:
   `createdb -U <user> <database>` then `psql -v ON_ERROR_STOP=1 -U <user> -d <database> -f db-dumps/postgresql-<database>.sql`
5. Copy the upload folders back to `storage/app/private` and `storage/app/public`; run `php artisan storage:link`.
6. `php artisan migrate --force` (applies anything newer than the backup), `php artisan optimize`, start the queue worker and scheduler.
7. Check: sign in to `/admin`, open today's bookings, open a guest's PDF invoice, run `php artisan app:production-check`.
8. Write the restore in the results log above.

Expected time from a backup on hand: well under the 8-hour RTO; most of it is provisioning.
