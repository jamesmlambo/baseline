# Hikvision terminal — proof of concept (ATT-06)

**Status: NOT STARTED. Needs the real device on site.** Until this passes, attendance comes from CSV exports and authorised manual entries (People › Attendance). The code adapter is `App\Services\Staff\Attendance\HikvisionAttendanceSource`, which deliberately refuses to run.

Device: **Hikvision DS-K1T342MFWX-E1** (face / fingerprint, with PIN or M1 card as the alternative — DEC-07).

## Ground rules
- Only **punch events** (person ID, time, direction, device serial, verify mode) are read. Face images and fingerprint templates stay on the device and never enter our database (DEC-07, COMPLIANCE_TODO › biometrics).
- The app never writes to the device (no enrolment, no user sync) without a separate decision.
- No credentials in the repository. Device username/password go in `.env` on the server only.

## What to record (fill in on site)

| Item | Result |
|---|---|
| Firmware version (Configuration › System › Device information) | _to fill_ |
| Device serial number | _to fill_ |
| Network: fixed IP / VLAN, reachable from the app server? | _to fill_ |
| Protocol chosen: **ISAPI** (app pulls events over HTTPS on the LAN) or **ISUP / EHome** (device pushes to the server) | _to fill_ |
| HTTPS with a valid certificate, or HTTP on an isolated network only | _to fill_ |
| Device clock: timezone set to SAST (UTC+2), NTP server | _to fill_ |
| Sample event (paste one, redact names): `employeeNoString`, `time`, `attendanceStatus`, `currentVerifyMode`, `serialNo` | _to fill_ |
| How the person ID maps to our employee (`Employee › ID on the clock terminal`) | _to fill_ |

## Tests to pass

1. **Basic read.** Pull (or receive) the last 24 hours of events; every punch on the device appears once in People › Clock events with the right time in SAST.
2. **Duplicates.** Read the same period twice: no new rows (dedup key = source + device serial + person ID + time + direction; device sequence numbers are not used on their own — ATT-02).
3. **Outage recovery.** Unplug the network for 30 minutes, punch on the device, reconnect: the missed punches arrive on the next read, with their original times.
4. **Timezone.** A punch at 07:59 on the device shows as 07:59 SAST, also across midnight (night shift).
5. **Direction.** Check whether the device sends in/out (attendance status) or only "authenticated". If only the latter, attendance uses first/last punch (already supported: direction "unknown").
6. **Unknown people.** A person on the device with no matching employee is kept as "Not matched" and linked automatically once their ID is entered on the employee.
7. **Load.** A month of events (≈ staff × 4 × 30) imports in under a minute.

## Acceptance
All seven tests pass on the installed firmware, results are written into the table above, `docs/INTEGRATIONS.md` is updated, and only then is the adapter switched on (scheduled read every 5–15 minutes, plus the existing hourly `attendance:derive`).

## Until then: CSV import
Export from the terminal's software (iVMS-4200 / Hik-Connect) and import under People › Attendance › Import clock CSV. Recognised headers are listed in `CsvAttendanceSource`. Re-importing the same file is safe.
