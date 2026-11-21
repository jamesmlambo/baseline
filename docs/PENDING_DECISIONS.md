# Pending client decisions

Seeded from `FINAL_SPECIFICATION.md` §3.2. Items with a setting show up in **Admin › Settings › System settings** with a warning until the client decides.

| Ref | Decision needed | Setting key(s) | Current build behaviour |
|---|---|---|---|
| PEND-01 | Hold durations | `booking.hold_minutes_online` (30, development default), `booking.hold_hours_manual_eft` (empty) | Every hold must have an expiry (enforced in the database). Online holds use the 30-minute development default; callers can pass a specific expiry. Expired holds are released every minute. |
| PEND-02 | Late EFT after a hold expires | — | Payment is flagged "Needs attention". A Manager/Owner can **Reinstate** (only if the room or another of its type is free), otherwise refund. |
| PEND-05b | Deposit needed to confirm | `payments.deposit_percent` (100, dev default) | Full payment confirms. Change to e.g. 50 for a deposit. |
| PEND-03 | Card vs ordinary EFT order | `payments.method_order` | Instant EFT → Card → EFT, configurable. |
| PEND-04 | Rooms, rates, contact details | `contact.*` (empty) | Nothing invented. Public site shows a DEMO banner. |
| PEND-05 | Policies, check-in/out times | `booking.check_in_time`, `booking.check_out_time` (empty) | Empty. |
| PEND-06 | Override authority | — | Owner only: confirm without payment (M4) and check out with money owed (M5), each with a recorded reason. |
| REC-04 (VERIFY) | Guest register required fields | `register.required_fields` (full name only) | Staff can capture ID, nationality, address, vehicle; only full name is enforced until a legal adviser confirms. |
| PEND-07 | Providers (payment, SMS, WhatsApp, channel manager) | `messaging.monthly_cap_*` (empty), `messaging.preferred_channel` (whatsapp) | Fake payment gateway and log-only SMS/WhatsApp drivers. Nothing is sent or charged. |
| PEND-14b | Feedback request after checkout | `messaging.feedback_request_enabled` (no) | Template exists, switched off. |
| WA price lists | How long links work; when to follow up | `price_lists.valid_hours` (24), `price_lists.follow_up_hours` (4) | Development defaults. |
| PEND-08 | Custom Laravel vs hybrid PMS | — | Custom Laravel behind interfaces. |
| PEND-09 | Budgets, named approver | — | Not a build item. |
| PEND-10 | Website languages | — | English only. |
| PEND-11 | Retention periods | — | Nothing deleted automatically. |
| PEND-12 | Escalation timers | `escalation.backup_minutes` (10), `escalation.owner_minutes` (30) | Recommended defaults. |
| PEND-13 | Non-functional targets | — | FINAL_SPECIFICATION §20 values used as design targets. |
| PEND-14 | Optional extras | — | Off by default. |
