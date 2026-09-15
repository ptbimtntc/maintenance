<?php

namespace App\Enums;

/**
 * Canonical list of application roles. Centralised here so that role names
 * are never hard-coded as raw strings across controllers, policies and views.
 */
enum RoleName: string
{
    case Administrator = 'Administrator';
    case MaintenanceManager = 'Maintenance Manager';
    case MaintenanceSupervisor = 'Maintenance Supervisor';
    case MaintenanceStaff = 'Maintenance Staff';
    case PeopleDevelopment = 'People Development';
    case Guest = 'Guest';

    public function label(): string
    {
        return $this->value;
    }
}
