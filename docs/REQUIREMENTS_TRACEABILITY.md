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
