<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed roles and permissions, and attach the baseline permission set to
     * each role. Idempotent: safe to re-run without creating duplicates.
     */
    public function run(): void
    {
        foreach (PermissionName::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            RoleName::Administrator->value => PermissionName::all(),

            RoleName::MaintenanceManager->value => [
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
            ],

            RoleName::MaintenanceSupervisor->value => [
                PermissionName::ViewSubordinateEmployees,
                PermissionName::ViewJobDescriptions,
                PermissionName::AssessCompetencies,
                PermissionName::ViewSkillMatrix,
                PermissionName::ViewCompetencyGap,
                PermissionName::ViewTraining,
                PermissionName::ViewCertificates,
                PermissionName::ViewDevelopmentPlans,
            ],

            RoleName::MaintenanceStaff->value => [
                PermissionName::ViewOwnEmployee,
                PermissionName::ViewJobDescriptions,
                PermissionName::ViewSkillMatrix,
                PermissionName::ViewTraining,
                PermissionName::ViewCertificates,
                PermissionName::ViewDevelopmentPlans,
            ],

            RoleName::PeopleDevelopment->value => [
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
            ],

            // Read-only, org-wide access for the "View as Guest" button on
            // the login page. Deliberately excludes ManageUsers/
            // ManageSettings/ManageMasterData and every Manage* permission,
            // so a guest can never edit anything no matter what a future
            // per-user menu override might say (User::canEditMenu() also
            // hard-blocks this role as a second layer of defense).
            RoleName::Guest->value => [
                PermissionName::ViewAllEmployees,
                PermissionName::ViewJobDescriptions,
                PermissionName::ViewSkillMatrix,
                PermissionName::ViewCompetencyGap,
                PermissionName::ViewTraining,
                PermissionName::ViewCertificates,
                PermissionName::ViewDevelopmentPlans,
                PermissionName::ViewReports,
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            $permissionValues = array_map(
                fn ($permission) => $permission instanceof PermissionName ? $permission->value : $permission,
                $permissions
            );

            $role->syncPermissions($permissionValues);
        }
    }
}
