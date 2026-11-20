<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Payment status (FINAL_SPECIFICATION §7), independent of booking status.
 * Transition rules arrive with the finance module in Milestone 4.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartPaid = 'part_paid';
    case PendingVerification = 'pending_verification';
    case PaidToChannel = 'paid_to_channel';
    case VerifiedPaid = 'verified_paid';
    case RefundPending = 'refund_pending';
    case PartRefunded = 'part_refunded';
    case Refunded = 'refunded';
    case ReconciliationRequired = 'reconciliation_required';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartPaid => 'Part-paid',
            self::PendingVerification => 'Pending verification',
            self::PaidToChannel => 'Paid to channel',
            self::VerifiedPaid => 'Verified paid',
            self::RefundPending => 'Refund pending',
            self::PartRefunded => 'Part-refunded',
            self::Refunded => 'Refunded',
            self::ReconciliationRequired => 'Reconciliation required',
        };
    }
}
