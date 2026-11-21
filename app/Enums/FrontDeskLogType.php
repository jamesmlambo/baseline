<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Front-desk events (REC-05): keys, items handed out, complaints and notes.
 */
enum FrontDeskLogType: string
{
    case KeyIssued = 'key_issued';
    case KeyReturned = 'key_returned';
    case KeyLost = 'key_lost';
    case ItemIssued = 'item_issued';
    case Complaint = 'complaint';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::KeyIssued => 'Key handed over',
            self::KeyReturned => 'Key returned',
            self::KeyLost => 'Key lost',
            self::ItemIssued => 'Items issued',
            self::Complaint => 'Complaint',
            self::Note => 'Note',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::KeyIssued, self::KeyReturned => 'info',
            self::KeyLost, self::Complaint => 'danger',
            self::ItemIssued => 'gray',
            self::Note => 'gray',
        };
    }

    /**
     * Types that stay open until someone resolves them.
     */
    public function needsResolution(): bool
    {
        return in_array($this, [self::Complaint, self::KeyLost], true);
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
