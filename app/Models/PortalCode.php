<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-time guest-portal code. Only a hash is stored; nobody, staff included, can read it (GST-02/03).
 */
#[Fillable(['booking_id', 'room_id', 'phone_hash', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'ip_hash'])]
#[Hidden(['code_hash', 'phone_hash'])]
class PortalCode extends Model
{
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }
}
