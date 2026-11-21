<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Staff roles from FINAL_SPECIFICATION.md §4. Guests never get staff accounts.
 */
enum StaffRole: string
{
    case Administrator = 'administrator';
    case Owner = 'owner';
    case Manager = 'manager';
    case Receptionist = 'receptionist';
    case HousekeepingWorker = 'housekeeping_worker';
    case MaintenanceWorker = 'maintenance_worker';
    case Inspector = 'inspector';
    case ContentEditor = 'content_editor';
    case KitchenStaff = 'kitchen_staff';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Receptionist => 'Receptionist',
            self::HousekeepingWorker => 'Housekeeping worker',
            self::MaintenanceWorker => 'Maintenance worker',
            self::Inspector => 'Inspector',
            self::ContentEditor => 'Content editor',
            self::KitchenStaff => 'Kitchen staff',
        };
    }

    /**
     * Roles allowed into the /admin panel.
     *
     * @return array<int, self>
     */
    public static function adminPanelRoles(): array
    {
        return [
            self::Administrator,
            self::Owner,
            self::Manager,
            self::Receptionist,
            self::ContentEditor,
        ];
    }

    /**
     * Roles that must use two-factor authentication (SEC-03).
     *
     * @return array<int, self>
     */
    public static function rolesRequiringTwoFactor(): array
    {
        return [
            self::Administrator,
            self::Owner,
            self::Manager,
            self::Receptionist,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }
}
