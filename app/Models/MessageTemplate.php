<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\MessageChannel;
use App\Enums\MessageClassification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A message wording for one automation on one channel (MSG-02). Changing the wording bumps the
 * version, and each sent message records the version it used.
 */
#[Fillable(['key', 'channel', 'language', 'version', 'name', 'body', 'classification', 'is_critical', 'is_active', 'cost_estimate_cents', 'provider_template_id'])]
class MessageTemplate extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'classification' => MessageClassification::class,
            'is_critical' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
            'cost_estimate_cents' => MoneyCast::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (MessageTemplate $template): void {
            if ($template->isDirty('body')) {
                $template->version = $template->getOriginal('version') + 1;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('messaging')->logOnly(['body', 'version', 'is_active', 'cost_estimate_cents'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * Placeholders used in the wording, e.g. ["guest_name", "booking_link"].
     *
     * @return list<string>
     */
    public function variables(): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $this->body, $matches);

        return array_values(array_unique($matches[1]));
    }
}
