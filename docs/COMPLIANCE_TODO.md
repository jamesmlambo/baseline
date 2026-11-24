# Compliance — items to verify (NOT legal advice)

Each item needs confirmation from a qualified South African legal or tax adviser before launch (PRIV-04). Nothing here is hard-coded as a rule.

| Area | What to verify | Build approach until then |
|---|---|---|
| Guest register | Which fields are legally required (incl. ID/passport for foreign guests) and how long to keep them | Configurable register fields (REC-04). No automatic deletion. |
| POPIA | Lawful basis, privacy notice wording, information officer, data-subject requests | Privacy Policy page as DEMO text. Consent records. Access logging (incl. refused access). Guest data export and anonymisation tools on the guest profile (M13). |
| POPIA vs tax records | Whether names on issued invoices/receipts must be kept after a deletion request, and for how long | Anonymisation leaves issued financial documents unchanged (immutable). |
| Quotation contacts | How long to keep the email and phone of people who asked for a quote but never booked | Stored encrypted on the quotation. Not yet covered by guest anonymisation or `privacy:retention` (quotes are not linked to a guest profile). |
| Email history | How long to keep the record of which address each invoice, quotation or receipt was emailed to | Stored encrypted in `document_deliveries`. Not yet covered by guest anonymisation or `privacy:retention`. |
| Audit log | How long the audit log (who changed what) must be kept | Kept indefinitely; anonymisation redacts the guest's entries. |
| PAIA | Whether a PAIA manual is needed and where to publish it | Placeholder page if required. |
| VAT / tax invoices | Whether the business is VAT-registered; tax-invoice field rules | Tax lines only shown when configured (PAY-02). |
| Biometric information | Lawful basis for Hikvision face/fingerprint use; employee notice; PIN/card alternative | Templates stay on the device. Only punch events are imported (DEC-07). |
| Labour / attendance records | Retention period and employee access rights | Configurable retention. Staff see their own records. |
| Working days and rest days | Weekly rest days, Sunday and public-holiday work and pay rules for staff who also work weekends | The roster allows any day of the week and does not enforce rest days or count hours. The DEMO weekend staff are rostered seven days a week, for illustration only. |
| Tourism grading | Valid TGCSA grading evidence | Stars hidden until evidence is uploaded (WEB-07). |
| Payment security | PCI scope with the chosen gateway | Hosted payment pages only; no card data stored (PAY-09). |
| Marketing messages | Consent rules for WhatsApp/SMS/email marketing | Marketing consent is separate and optional (OBK-03, MSG-04). |
| Retention periods | Per data type (PEND-11) | `retention.*` settings, legal holds and `php artisan privacy:retention` preview (M13). Enforcement switched off; nothing changed or deleted until approved. |
| Backups | Where off-site backups may be stored (POPIA cross-border transfer if outside South Africa) | Encrypted backups; off-site disk chosen at go-live (docs/BACKUP_RESTORE.md). |
