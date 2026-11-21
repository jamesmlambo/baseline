# Integrations

No outside service is live. Every integration sits behind an interface with a fake or log driver until the client approves a provider (PEND-07) and it has been tested.

| Integration | Interface | Driver now | Status | Spec |
|---|---|---|---|---|
| Payment gateway (Ozow / PayFast / Yoco candidates) | `PaymentGateway` | **fake** (`/dev/fake-gateway`, HMAC-signed webhooks, refuses production) | Interface, webhook pipeline and refunds built (M4). Real adapter waits for provider choice. | PAY-05, PAY-06 |
| SMS / OTP | `MessageProvider` (SMS) | **log** (`SMS_DRIVER`) | Messaging, templates, retries, spend cap built (M6). Real provider waits for PEND-07. | MSG-01, GST-02 |
| WhatsApp | `MessageProvider` (WhatsApp) | **log** (`WHATSAPP_DRIVER`) + click-to-chat links for staff | As SMS. A real WhatsApp Business provider will also need template approval. | MSG-01, WA-06 |
| Email | Laravel mail | `log` mailer | Active locally (log only) | MSG-01 |
| Channel manager (Booking.com, LekkeSlaap) | `ChannelManager` | null | Not built (M12). Architecture pending (PEND-08). | CHN-01–04 |
| Hikvision DS-K1T342MFWX-E1 | `AttendanceSource` | **CSV import + authorised manual entry** (M9) | Adapter stub refuses to run until the on-device proof of concept passes (`docs/HIKVISION_POC.md`). |  ATT-01, ATT-06 |
| Web push | `PushNotifier` | **log** (`LogPushNotifier`) | Interface and delivery log built (M11). Deliveries show as "skipped". A real channel needs a package and VAPID keys (approval). | REP-03 |
| Antivirus scanning | `FileScanner` | none | Not built. Never shown as active until a real scanner is connected. | SEC-08 |

## Rules
- Never send real SMS, WhatsApp or payments from local or test environments.
- Every driver is chosen through `.env` plus an admin setting.
- Do not claim an integration works until it has been tested against the provider's sandbox and the result is recorded here.
