<?php

namespace App\Enums;

use App\Enums\PermissionName as P;

/**
 * The set of menus an Administrator can grant per-user edit rights on (see
 * UserMenuPermission). Deliberately narrower than every module in the app -
 * Settings, User Management, and Audit Log stay gated purely by role
 * permissions (Administrator-only), since they're inherently system-level,
 * not something to hand out per user.
 */
enum MenuKey: string
{
    case Employees = 'employees';
    case Organization = 'organization';
    case JobDescriptions = 'job-descriptions';
    case SkillsCompetencies = 'skills-competencies';
    case Training = 'training';
    case Certificates = 'certificates';
    case DevelopmentPlans = 'development-plans';

    public function label(): string
    {
        return match ($this) {
            self::Employees => 'Employees',
            self::Organization => 'Organization & Master Data',
            self::JobDescriptions => 'Job Descriptions',
            self::SkillsCompetencies => 'Skills & Competencies',
            self::Training => 'Training (Programs, Sessions, Records)',
            self::Certificates => 'Certificates',
            self::DevelopmentPlans => 'Development Plans',
        };
    }

    /**
     * Every menu defaults to read-only except Job Descriptions, per the
     * brief: staff should always be able to work with their own job
     * description without an administrator having to grant it explicitly.
     */
    public function editableByDefault(): bool
    {
        return $this === self::JobDescriptions;
    }

    /**
     * The pre-existing role permission(s) that already granted edit access
     * to this menu before per-user overrides existed. Used as the default
     * when no explicit override row exists, so introducing this system
     * doesn't silently take away capability a role already had (see
     * User::canEditMenu()) - an administrator can still narrow it per user.
     *
     * @return string[]
     */
    public function managePermissionValues(): array
    {
        return match ($this) {
            self::Employees => [P::ManageEmployees->value],
            self::Organization => [P::ManageMasterData->value],
            self::JobDescriptions => [P::ManageJobDescriptions->value],
            self::SkillsCompetencies => [P::ManageSkills->value, P::AssessCompetencies->value],
            self::Training => [P::ManageTraining->value, P::ManageTrainingRecords->value],
            self::Certificates => [P::ManageCertificates->value],
            self::DevelopmentPlans => [P::ManageDevelopmentPlans->value],
        };
    }

    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Maps every "Manage*"/mutating permission to the menu that governs it,
     * so a single Gate::after hook (see AppServiceProvider) can retrofit
     * every existing @can(...) view check and $this->authorize(...) call
     * across the app with the per-user menu-edit layer, without having to
     * touch each of those call sites individually. Permissions with no
     * entry here (ManageUsers, ManageSettings) stay purely role-gated.
     */
    public static function forManagePermission(string $permission): ?self
    {
        return match ($permission) {
            P::ManageEmployees->value => self::Employees,
            P::ManageMasterData->value => self::Organization,
            P::ManageJobDescriptions->value => self::JobDescriptions,
            P::ManageSkills->value, P::AssessCompetencies->value => self::SkillsCompetencies,
            P::ManageTraining->value, P::ManageTrainingRecords->value => self::Training,
            P::ManageCertificates->value => self::Certificates,
            P::ManageDevelopmentPlans->value => self::DevelopmentPlans,
            default => null,
        };
    }
}
