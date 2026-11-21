<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance (PEND-13 targets): PostgreSQL does not index foreign keys by itself. Index the ones
 * used in joins, lookups and cascades. "Done by" user columns are left alone — nothing searches them.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const INDEXES = [
        'accommodation_type_amenity' => ['amenity_id'],
        'assets' => ['room_id'],
        'attendance_days' => ['shift_id'],
        'attendance_punches' => ['attendance_import_id'],
        'booking_extras' => ['booking_id', 'extra_id'],
        'booking_rooms' => ['booking_id', 'accommodation_type_id'],
        'booking_status_histories' => ['booking_id'],
        'bookings' => ['guest_id'],
        'channel_exceptions' => ['channel_reservation_id', 'room_id'],
        'channel_reservations' => ['booking_id'],
        'credit_notes' => ['booking_id', 'invoice_id', 'refund_id'],
        'employees' => ['department_id'],
        'front_desk_logs' => ['booking_id', 'room_id'],
        'guest_register_entries' => ['booking_id', 'guest_id'],
        'guest_requests' => ['booking_id', 'room_id'],
        'ical_events' => ['room_block_id'],
        'ical_links' => ['room_id'],
        'inspection_scores' => ['inspection_id'],
        'inspections' => ['room_id'],
        'invoices' => ['booking_id', 'guest_id'],
        'kitchen_tickets' => ['room_id'],
        'maintenance_schedules' => ['asset_id', 'room_id'],
        'messages' => ['booking_id', 'guest_id'],
        'payment_webhook_events' => ['payment_id'],
        'payments' => ['booking_id', 'invoice_id'],
        'portal_codes' => ['room_id'],
        'price_lists' => ['booking_id'],
        'quotations' => ['booking_id'],
        'receipts' => ['booking_id', 'invoice_id'],
        'refunds' => ['payment_id'],
        'room_blocks' => ['room_id'],
        'room_nights' => ['booking_room_id', 'room_block_id'],
        'rooms' => ['accommodation_type_id'],
        'roster_changes' => ['shift_id'],
        'roster_patterns' => ['shift_id'],
        'service_booking_resources' => ['service_booking_id'],
        'service_bookings' => ['invoice_id'],
        'stock_movements' => ['task_id'],
        'tasks' => ['assigned_to', 'booking_id', 'asset_id', 'parent_task_id', 'maintenance_schedule_id'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            if ($columns === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            if ($columns === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
