<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\DailyReport;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * REP-03: the daily summary email. Headline totals only — no guest names or contact details —
 * and a link to the full report, which needs a sign-in to open.
 */
class DailyReportMail extends Mailable
{
    public function __construct(public readonly DailyReport $report, public readonly string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Baseline Lodge — daily report '.$this->report->report_date->format('j M Y'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.daily-report', with: [
            'date' => $this->report->report_date,
            'data' => $this->report->data,
            'url' => $this->url,
        ]);
    }
}
