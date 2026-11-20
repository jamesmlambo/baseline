# Integrations

No outside service is live. Every integration sits behind an interface with a fake or log driver until the client approves a provider (PEND-07) and it has been tested.

| Integration | Interface | Driver now | Status | Spec |
|---|---|---|---|---|
| Payment gateway (Ozow / PayFast / Yoco candidates) | `PaymentGateway` | fake | Not built (M4) | PAY-05, PAY-06 |
| SMS / OTP | `SmsProvider` | log | Not built (M6) | MSG-01, GST-02 |
| WhatsApp | `WhatsAppProvider` | log | Not built (M6) | MSG-01, WA-06 |
| Email | Laravel mail | `log` mailer | Active locally (log only) | MSG-01 |
| Channel manager (Booking.com, LekkeSlaap) | `ChannelManager` | null | Not built (M12). Architecture pending (PEND-08). | CHN-01–04 |
| Hikvision DS-K1T342MFWX-E1 | `AttendanceSource` | CSV / manual | Not built (M9). Needs a firmware test on the real device. | ATT-01, ATT-06 |
| Web push | webpush channel | — | Not built (M11) | REP-03 |
| Antivirus scanning | `FileScanner` | none | Not built. Never shown as active until a real scanner is connected. | SEC-08 |

## Rules
- Never send real SMS, WhatsApp or payments from local or test environments.
- Every driver is chosen through `.env` plus an admin setting.
- Do not claim an integration works until it has been tested against the provider's sandbox and the result is recorded here.
