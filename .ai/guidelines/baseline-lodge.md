# Baseline Lodge project rules

Read `docs/MASTER_PROMPT.md`, `docs/FINAL_SPECIFICATION.md` and `docs/IMPLEMENTATION_PROGRESS.md` at the start of every session. The client specification is `docs/SPEC_v5.3.txt` / `docs/SPEC_v5.3.pdf`.

Precedence: client decisions in SPEC v5.3 → `FINAL_SPECIFICATION.md` → `MASTER_PROMPT.md` → reviewer suggestions.

## Non-negotiable rules
- The guesthouse is **Baseline Lodge**. Never use "Nyala" or any other name.
- **PostgreSQL only**, tests included. App DB `baselineCL`, test DB `baselineCL_testing`. Never touch `baseline_lodge*` databases: they belong to another project.
- **Never invent business facts** (room names, prices, contact details, policies, photos, grading). Use clearly marked DEMO data or settings flagged PENDING CLIENT DECISION.
- **No live integrations.** Payments, SMS, WhatsApp, channel managers and Hikvision use interfaces with fake/log drivers until approved.
- **No destructive commands** (dropping databases, `migrate:fresh` on `baselineCL`, deleting data) without explicit approval.
- Work milestone by milestone (FINAL_SPECIFICATION §22). Run migrations, Pest, Pint and `npm run build` before calling anything done. Never claim a test passed unless it ran.
- Money is integer cents (`bigint`). Times are stored as `timestampTz` and shown in Africa/Johannesburg.

## Conventions
- `declare(strict_types=1)` in every PHP file. PHP enums for statuses, in `app/Enums`.
- Business logic lives in `app/Actions` and `app/Services`. Keep Filament resources, controllers and Livewire components thin.
- Policies for every model. Form Requests for HTTP validation.
- Use requirement IDs from FINAL_SPECIFICATION (e.g. `BOOK-03`) in test names and `docs/REQUIREMENTS_TRACEABILITY.md`.

## Docs to keep updated
`docs/IMPLEMENTATION_PROGRESS.md` (after every milestone), `docs/ARCHITECTURE.md`, `docs/PENDING_DECISIONS.md`, `docs/REQUIREMENTS_TRACEABILITY.md`, `docs/INTEGRATIONS.md`, `docs/COMPLIANCE_TODO.md`, `docs/TESTING.md`.
