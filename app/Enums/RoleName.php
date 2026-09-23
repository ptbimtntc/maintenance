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

    /**
     * The baseline permission set for this role. Single source of truth for
     * both RolesAndPermissionsSeeder and the "Reset to default" action on the
     * Roles & Permissions screen, so the two never drift apart.
     *
     * @return string[]
     */
    public function defaultPermissions(): array
    {
        $permissions = match ($this) {
            self::Administrator => PermissionName::cases(),

            self::MaintenanceManager => [
                PermissionName::ViewSubordinateEmployees,
                PermissionName::ManageEmployees,
                PermissionName::ViewJobDescriptions,
                PermissionName::ManageJobDescriptions,
                PermissionName::ManageSkills,
                PermissionName::AssessCompetencies,
                PermissionName::ViewSkillMatrix,
                PermissionName::ViewCompetencyGap,
                PermissionName::ViewTraining,
                PermissionName::ManageTraining,
                PermissionName::ManageTrainingRecords,
                PermissionName::ViewCertificates,
                PermissionName::ViewDevelopmentPlans,
                PermissionName::ManageDevelopmentPlans,
                PermissionName::ViewReports,
                PermissionName::ViewSafety,
                PermissionName::ManageSafety,
            ],

            self::MaintenanceSupervisor => [
                PermissionName::ViewSubordinateEmployees,
                PermissionName::ViewJobDescriptions,
                PermissionName::AssessCompetencies,
                PermissionName::ViewSkillMatrix,
                PermissionName::ViewCompetencyGap,
                PermissionName::ViewTraining,
                PermissionName::ViewCertificates,
                PermissionName::ViewDevelopmentPlans,
                PermissionName::ViewSafety,
            ],

            self::MaintenanceStaff => [
                PermissionName::ViewOwnEmployee,
                PermissionName::ViewJobDescriptions,
                PermissionName::ViewSkillMatrix,
                PermissionName::ViewTraining,
                PermissionName::ViewCertificates,
                PermissionName::ViewDevelopmentPlans,
                PermissionName::ViewSafety,
            ],

            self::PeopleDevelopment => [
                PermissionName::ViewAllEmployees,
                PermissionName::ViewJobDescriptions,
                PermissionName::ViewSkillMatrix,
                PermissionName::ViewCompetencyGap,
                PermissionName::ViewTraining,
                PermissionName::ManageTraining,
                PermissionName::ManageTrainingRecords,
                PermissionName::ViewCertificates,
                PermissionName::ManageCertificates,
                PermissionName::ViewDevelopmentPlans,
                PermissionName::ManageDevelopmentPlans,
                PermissionName::ViewReports,
                PermissionName::ViewSafety,
            ],

            // Read-only, org-wide access for the "View as Guest" button on
            // the login page. Deliberately excludes ManageUsers/
            // ManageSettings/ManageMasterData and every Manage* permission,
            // so a guest can never edit anything no matter what a future
            // per-user menu override might say (User::canEditMenu() also
            // hard-blocks this role as a second layer of defense).
            self::Guest => [
                PermissionName::ViewAllEmployees,
                PermissionName::ViewJobDescriptions,
                PermissionName::ViewSkillMatrix,
                PermissionName::ViewCompetencyGap,
                PermissionName::ViewTraining,
                PermissionName::ViewCertificates,
                PermissionName::ViewDevelopmentPlans,
                PermissionName::ViewReports,
                PermissionName::ViewSafety,
            ],
        };

        return array_map(fn (PermissionName $permission) => $permission->value, $permissions);
    }
}
