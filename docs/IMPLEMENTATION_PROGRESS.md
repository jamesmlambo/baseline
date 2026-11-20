# Implementation progress

New session? Read `docs/MASTER_PROMPT.md`, `docs/FINAL_SPECIFICATION.md` and this file, then continue from **Next**.

## Current status

- **Milestone 1 — Foundation:** done.
- **Milestone 2 — Rooms & booking engine:** done (engine and admin screens). There is no booking UI yet: bookings are created through `PlaceBooking`, and the screens come in M4/M5.
- **Next:** **Milestone 3 — Public website** (20 pages, CMS, SEO, redirects; FINAL_SPECIFICATION §5).

## Environment

| Item | Value |
|---|---|
| Laravel | 13.43 |
| PHP | 8.5.12 (Herd) |
| PostgreSQL | 18.4, user `postgres`, local trust auth (no password); `btree_gist` extension used |
| App database | `baselineCL` |
| Test database | `baselineCL_testing` (used by `phpunit.xml`) |
| Not ours | `baseline_lodge`, `baseline_lodge_test`, `baseline_lodge_testing` belong to another Laravel project. **Do not touch them.** |
| Local URL | http://127.0.0.1:8000 (`php artisan serve`, or the VS Code task **Dev: all**) |
| Git | `main`, author James. No remote. |

## Milestone 1 — Foundation (20 Nov 2026)

Done: PostgreSQL, Laravel Boost, Filament 5 panels `/admin` and `/staff` with TOTP 2FA (required on `/admin`), 8 staff roles, staff accounts screen, system settings with PENDING flags, audit log, local demo staff accounts, public layout and placeholder homepage, VS Code workspace, project rules (`.ai/guidelines`, merged into `CLAUDE.md`/`AGENTS.md`), README and docs.

## Milestone 2 — Rooms & booking engine (21 Nov 2026)

### Database
- `accommodation_types`, `rooms`, `seasonal_rates`. Seasons cannot overlap per type (GiST exclusion constraint).
- `bookings`, `booking_rooms`, `room_blocks`, `room_nights`, `booking_status_histories`.
- **Double-booking protection in PostgreSQL:**
  - `room_nights_one_active_claim`: unique (room, night) where not released (INV-03).
  - `booking_rooms_no_overlap`: exclusion constraint on overlapping active stays per room (INV-04).
  - CHECKs: departure after arrival, a hold must have an expiry, no negative money, valid occupancy.

### Code
- `App\Support\Money` + `MoneyCast` (integer cents, `R 1 234.50` formatting). `App\Support\StayDates`.
- Enums: `BookingStatus` (with allowed transitions), `PaymentStatus`, `BookingSource`, `RoomBlockReason`.
- `Services\Pricing\RateCalculator`: seasonal rate per night, else base rate, plus extra guests.
- `Services\Availability\AvailabilityService`: the single availability source (search, available rooms, room check).
- `Services\Inventory\RoomNightLedger`: claims and releases room nights; detects conflicts by SQLSTATE.
- `Actions\Bookings\PlaceBooking`: creates the booking and assigns a physical room per requested room in one transaction. Tries the next room if a concurrent request wins. Holds always get an expiry (setting `booking.hold_minutes_online`, PEND-01).
- `Services\Bookings\BookingStateMachine`: the only way to change status. Rejects invalid moves, writes history, and releases inventory on Cancelled, No-show and Expired.
- `Actions\Bookings\ReleaseExpiredHolds` + scheduled `bookings:release-expired-holds` every minute.
- `Actions\Bookings\ReassignRoom`: reason required, audited, old assignment kept as history.
- `Actions\Inventory\BlockRoom` / `ReleaseRoomBlock`: refuses to block booked nights.

### Admin (`/admin › Bookings`)
- **Rooms & rates:** room types with Rooms and Seasonal rates tabs. Rand inputs are stored as cents. Types and rooms are deactivated, never deleted.
- **Room blocks:** take a room out of service; release button.
- Access: Administrator, Owner and Manager manage; Receptionist views only.

### Demo data
`DemoAccommodationSeeder` (local only): "Demo Standard Room" ×4 and "Demo Family Room" ×2, rooms "Demo Room 01–06", flagged `is_demo`. Prices are placeholders.

### Tests
81 passing, including a **two-connection race test** (`tests/Concurrency`) proving a rival claim waits on, then is refused by, an uncommitted booking of the last room.

### Deferred (by design)
- Rate plans (e.g. B&B vs room-only) and enforcing a season's minimum nights: M4 booking flow.
- Taxes and fees: M4 finance.
- Room readiness status (Dirty/Ready…): M8 housekeeping.
- Booking list and calendar UI: M4/M5.
