@props(['title', 'document', 'contact', 'vatNumber' => null, 'isDemo' => true])
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $document->number }}</title>
    <style>
        /* Built-in PDF font: nothing embedded, so files stay small for email. */
        * { font-family: Helvetica, Arial, sans-serif; }
        body { font-size: 11px; color: #1c1917; margin: 0; }
        .demo { background: #fbbf24; text-align: center; font-weight: bold; padding: 4px; font-size: 10px; }
        .wrap { padding: 28px 36px; }
        .head { width: 100%; border-bottom: 2px solid #0f514b; padding-bottom: 12px; }
        .brand { font-size: 20px; font-weight: bold; color: #0f514b; }
        .muted { color: #57534e; }
        h1 { font-size: 22px; margin: 0; color: #0f514b; text-align: right; }
        table.meta { width: 100%; margin-top: 18px; }
        table.meta td { vertical-align: top; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 22px; }
        table.lines th { text-align: left; border-bottom: 1px solid #a8a29e; padding: 6px 4px; font-size: 10px; text-transform: uppercase; color: #57534e; }
        table.lines td { border-bottom: 1px solid #e7e5e4; padding: 7px 4px; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { width: 45%; margin-left: 55%; margin-top: 12px; border-collapse: collapse; }
        table.totals td { padding: 4px; }
        table.totals tr.grand td { border-top: 2px solid #1c1917; font-weight: bold; font-size: 13px; }
        .box { margin-top: 22px; padding: 10px 12px; background: #f4eee3; }
        .foot { margin-top: 28px; font-size: 9px; color: #78716c; }
    </style>
</head>
<body>
    @if ($isDemo)
        <div class="demo">DEMO DOCUMENT — NOT VALID FOR PAYMENT OR TAX PURPOSES</div>
    @endif
    <div class="wrap">
        <table class="head">
            <tr>
                <td>
                    <div class="brand">Baseline Lodge</div>
                    <div class="muted">
                        @if ($contact['address']){{ $contact['address'] }}<br>@endif
                        @if ($contact['phone']){{ $contact['phone'] }}@endif
                        @if ($contact['phone'] && $contact['email']) · @endif
                        @if ($contact['email']){{ $contact['email'] }}@endif
                        @if ($vatNumber)<br>VAT no. {{ $vatNumber }}@endif
                    </div>
                </td>
                <td style="text-align:right;vertical-align:top;">
                    <h1>{{ $title }}</h1>
                    <div><strong>{{ $document->number }}</strong></div>
                    <div class="muted">Issued {{ $document->issued_at->setTimezone('Africa/Johannesburg')->format('j F Y') }}</div>
                </td>
            </tr>
        </table>

        {{ $slot }}

        <p class="foot">
            Baseline Lodge will never ask for your card details by WhatsApp, SMS or chat, and will never change its banking details by message.
            If in doubt, phone us on our published number before paying.
        </p>
    </div>
</body>
</html>
