<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageChannel: string
{
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
        };
    }

    /**
     * Setting holding this channel's monthly spend cap in cents (MSG-06).
     */
    public function capSettingKey(): string
    {
        return 'messaging.monthly_cap_'.$this->value.'_cents';
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [self::WhatsApp->value => self::WhatsApp->label(), self::Sms->value => self::Sms->label()];
    }
}
