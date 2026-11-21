# Production checklist (Milestone 13)

Run `php artisan app:production-check` on the production server. It lists every blocking item (**FAIL**) and every item that needs a conscious decision (**CHECK**), and exits with an error while anything blocks. Acceptance criterion 10: no DEMO content, demo accounts or fake drivers active in production; every PENDING item either decided or still visibly flagged.

## 1. Client decisions (docs/PENDING_DECISIONS.md)
- [ ] Every PEND item decided, or knowingly left flagged (Settings shows a warning on each).
- [ ] Real content: room types, rooms, rates, photos, contact details, policies, menu, services, shifts, checklists, cleaning times (all DEMO data replaced).
- [ ] Compliance items in docs/COMPLIANCE_TODO.md confirmed by a South African legal/tax adviser (POPIA, guest register, VAT, biometrics, retention).

## 2. Remove demo material
- [ ] Never run `DevelopmentUserSeeder`, `DemoAccommodationSeeder`, `DemoWebsiteSeeder`, `DemoMenuSeeder`, `DemoStaffSeeder`, `DemoLeisureSeeder` on production (they refuse to run outside `local`).
- [ ] No `@baseline-lodge.test` accounts active; every staff member has their own account (SEC-01) and owners/managers/reception have set up 2FA (SEC-03).
- [ ] `app:production-check` shows no demo records in any table.

## 3. Providers (docs/INTEGRATIONS.md) — each needs approval and a sandbox test first
- [ ] Payment gateway (replaces `fake`; `PAYMENT_GATEWAY`). Webhook secret set; sandbox payment + refund tested.
- [ ] SMS provider (replaces `log`; guest-portal codes depend on it). WhatsApp provider and approved templates.
- [ ] Mail: real SMTP/API mailer (not `log`), SPF/DKIM/DMARC for the sending domain.
- [ ] Monthly SMS/WhatsApp spend caps set.
- [ ] Optional, when approved: web push (VAPID keys), virus scanner, channel manager, iCal fetching, Hikvision (after docs/HIKVISION_POC.md).

## 4. Server and security
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, new `APP_KEY` stored in a password manager.
- [ ] HTTPS with automatic renewal; `SESSION_SECURE_COOKIE=true`; HSTS is sent automatically over HTTPS.
- [ ] PostgreSQL 16+ with a dedicated user that owns only this database; not reachable from the internet.
- [ ] `php artisan migrate --force`, `php artisan db:seed --class=RoleSeeder --force`, then the all-environment seeders (`SettingSeeder`, `WebsiteContentSeeder`, `MessageTemplateSeeder`, `TaskChecklistSeeder`, `ChannelSeeder`).
- [ ] `php artisan optimize` (config, route, view and event caches); OPcache on; `npm run build` assets deployed.
- [ ] `php artisan storage:link`; `storage/` writable; uploads stay on the private disk.
- [ ] Queue worker running under a supervisor (`php artisan queue:work --tries=3`), restarted on deploy (`php artisan queue:restart`).
- [ ] Scheduler: cron `* * * * * php /path/artisan schedule:run` (holds, messages, portal escalation, reports, attendance, housekeeping, channels, backups, retention).
- [ ] Content-Security-Policy: not set yet (Filament/Livewire need nonces). Decide and test before enabling.
- [ ] Login rate limiting and password reset tested on the live domain.

## 5. Backups (docs/BACKUP_RESTORE.md)
- [ ] `BACKUP_ARCHIVE_PASSWORD` set and stored safely; off-site disk added to `BACKUP_DISKS`.
- [ ] First `backup:run` succeeded; `backup:restore-test` passed on the production server and is logged.
- [ ] Backup failure emails reach the owner (`BACKUP_NOTIFY_EMAIL`).

## 6. Monitoring
- [ ] Owner dashboard "Exceptions" box empty (failed jobs, failed messages, payments needing attention, report deliveries, channel problems).
- [ ] Log rotation (`LOG_CHANNEL=daily`), disk space alerts, uptime check on `/up`.
- [ ] The 17:00 report arrives by email on day one.

## 7. Go-live (FINAL_SPECIFICATION §22)
- [ ] Old website URLs mapped with 301 redirects (Admin › Website › Redirects).
- [ ] Historical guests/bookings imported and checked (separate task).
- [ ] Pilot: old and new systems in parallel; UAT signed off; staff trained.
- [ ] The lodge owns the domain, hosting, repository, payment and messaging accounts.

## Performance notes (PEND-13 targets)
- Local development (debug on, no caches): public pages 130–370 ms; staff screens tested with lazy-loading prevention on, so N+1 queries fail tests.
- Foreign keys used in joins and lookups are indexed (migration `2026_12_01_110000`).
- Measure again on the production server with caches on; target ≤ 2 s at the 95th percentile for staff screens and Lighthouse ≥ 90 on mobile for public pages.
