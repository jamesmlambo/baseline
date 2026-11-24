<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\DocumentDelivery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Invoices, quotations and receipts remember every time they were emailed (PAY-02).
 */
trait HasEmailHistory
{
    /**
     * @return MorphMany<DocumentDelivery, $this>
     */
    public function deliveries(): MorphMany
    {
        return $this->morphMany(DocumentDelivery::class, 'document')->with('sender')->latest('sent_at')->latest('id');
    }

    public function recordEmail(string $email, string $reason = DocumentDelivery::REASON_STAFF, ?User $by = null): DocumentDelivery
    {
        return $this->deliveries()->create([
            'channel' => DocumentDelivery::CHANNEL_EMAIL,
            'recipient' => mb_strtolower(trim($email)),
            'reason' => $reason,
            'sent_by' => $by?->id,
            'sent_at' => now(),
        ]);
    }

    /**
     * Each address the document was emailed to, how many times, and when last.
     * Addresses are encrypted in the database, so they are grouped here.
     *
     * @return Collection<int, array{email: string, times: int, last_sent_at: CarbonImmutable}>
     */
    public function emailSummary(): Collection
    {
        $deliveries = $this->relationLoaded('deliveries') ? $this->deliveries : $this->deliveries()->get();

        return $deliveries
            ->where('channel', DocumentDelivery::CHANNEL_EMAIL)
            ->groupBy('recipient')
            ->map(fn (Collection $sends, string $email): array => [
                'email' => $email,
                'times' => $sends->count(),
                'last_sent_at' => $sends->max('sent_at'),
            ])
            ->sortByDesc('last_sent_at')
            ->values();
    }

    /**
     * Short status for lists: "Not sent", or how many emails to how many addresses.
     */
    public function emailStatus(): string
    {
        $summary = $this->emailSummary();

        if ($summary->isEmpty()) {
            return 'Not sent';
        }

        $times = $summary->sum('times');

        return $summary->count() === 1
            ? "Sent {$times}× to {$summary->first()['email']}"
            : "Sent {$times}× to {$summary->count()} addresses";
    }
}
