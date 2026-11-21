# Report metrics (REP-02)

How every figure on the owner dashboard and the daily report is worked out. Code: `App\Services\Reports\ReportMetrics`. Money is in cents and shown in rand. No industry benchmarks are shown (REP-06).

## Date bases

| Basis | Meaning | Used for |
|---|---|---|
| **Stay date** | The night (or day) the guest is with us | Rooms, occupancy, ADR, RevPAR, stays by status |
| **Transaction date** | When money moved or a document was issued (SAST) | Payments, refunds, invoices, credit notes, leisure done |
| **Created date** | When the booking, enquiry or price list was made | Booking sources, conversion, campaigns, enquiries |
| **As at** | The position at the moment the figures were produced | Money owed, open tasks, open complaints, rooms by status, exceptions |

A period runs from 00:00 on the first day to 23:59 on the last day, South African time. Operations figures that belong to one day (arrivals, departures, attendance) use the **last day** of the period.

## Booking statuses

Kept apart everywhere (REP-02):

- **Confirmed** = confirmed, checked in or checked out. Only these count as sold.
- **Pending** = quote, hold or pending payment.
- **Cancelled**, **No-show**, **Expired** — shown separately, never counted as sold.

## Rooms (stay date)

| Metric | Formula |
|---|---|
| Rooms available | active rooms × nights in the period − room-nights blocked (out of service, owner use, maintenance) |
| Room-nights sold | room-nights held by confirmed bookings in the period |
| Occupancy % | room-nights sold ÷ rooms available × 100 |
| Room revenue | for each sold room-night: that room's accommodation total ÷ its number of nights (spread evenly) |
| ADR (average daily rate) | room revenue ÷ room-nights sold |
| RevPAR (revenue per available room) | room revenue ÷ rooms available |

Room revenue excludes extras, leisure and room service, and is before any refunds or credit notes. Rooms made inactive are left out of availability for the whole period.

## Money (transaction date)

| Metric | Formula |
|---|---|
| Payments received | succeeded payments, by the time the money was received; also split by method |
| Refunds paid | refunds with status *processed*, by processing time |
| Invoiced | invoices issued in the period, excluding voided invoices |
| Credit notes | credit notes issued in the period |
| Leisure done | leisure bookings marked done in the period (price at booking) |
| EFTs awaiting verification | payments waiting for staff to check the bank (as at now) |
| Owed now | sum of positive balances for guests in house or who left in the last 90 days (as at now) |

## Operations

Arrivals (hold/pending/confirmed/in house/checked out arriving that day), departures, in house, rooms by cleaning status, housekeeping done in the period and open, all overdue tasks, inspections passed/done and average score (each item's score ÷ its scale, averaged), open maintenance tasks, faults reported, complaints new/open, guest requests and unanswered messages, attendance by status for the day, and exceptions (payments needing attention, failed messages in the period, failed background jobs, failed report deliveries).

## Bookings and marketing (created date) — REP-06

| Metric | Formula |
|---|---|
| Online conversion % | website bookings that became confirmed ÷ website bookings started (a booking starts as a hold when the guest submits the form) |
| WhatsApp price-list conversion % | price lists that led to a booking ÷ price lists sent |
| Booking sources | bookings made, by source, with how many are confirmed |
| Campaigns | website bookings by `utm_campaign` (or `utm_source`, or the referring site) from the visitor's first page in that visit |
| Enquiries | website enquiries received |

Campaign tracking keeps only the utm values and the referring site's host name — no full URLs, IP addresses or tracking cookies. Visitors who change browser or device between visits are not linked.

## Daily report (REP-03/04)

- From the configured time (`reports.daily_time`, 17:00 SAST) the day's figures (that day only, plus month to date and tomorrow's arrivals) are stored once as a snapshot and sent to every active owner by email (headline totals only, no guest details, with a link that needs sign-in) and web push.
- Each send is logged. Failures are retried after 5, 10, 20 and 40 minutes (5 tries in all), and failures show on the dashboard.
- Web push uses a log driver until a push provider is approved; those deliveries show as *skipped*, never as sent.
- **Day close** (optional): the owner or a manager can record the day's money totals once reconciliation is done. It never changes the 17:00 snapshot and cannot be undone.
- Urgent guest escalations run separately, every minute (REP-05).
