<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of one payment attempt. Only Succeeded counts as money received (PAY-07):
 * proof of EFT leaves a payment at PendingVerification.
 */
enum PaymentRecordStatus: string
{
    case Initiated = 'initiated';
    case PendingVerification = 'pending_verification';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case ReconciliationRequired = 'reconciliation_required';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Awaiting payment',
            self::PendingVerification => 'Pending verification',
            self::Succeeded => 'Received',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::ReconciliationRequired => 'Needs attention',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Initiated => 'gray',
            self::PendingVerification => 'warning',
            self::Succeeded => 'success',
            self::Failed, self::Cancelled => 'danger',
            self::ReconciliationRequired => 'warning',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
