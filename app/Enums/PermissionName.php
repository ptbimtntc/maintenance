<?php

namespace App\Enums;

/**
 * Canonical list of application permissions, grouped by module. Centralised
 * here so permission strings are defined once and reused everywhere
 * (seeders, policies, Blade @can checks) instead of being hard-coded.
 */
enum PermissionName: string
{
    // Users & system administration
    case ManageUsers = 'users.manage';
    case ManageSettings = 'settings.manage';
    case ManageMasterData = 'master-data.manage';

    // Employees
    case ViewAllEmployees = 'employees.view-all';
    case ViewSubordinateEmployees = 'employees.view-subordinates';
    case ViewOwnEmployee = 'employees.view-own';
    case ManageEmployees = 'employees.manage';

    // Job descriptions
    case ViewJobDescriptions = 'job-descriptions.view';
    case ManageJobDescriptions = 'job-descriptions.manage';

    // Skills & competencies
    case ManageSkills = 'skills.manage';
    case AssessCompetencies = 'competencies.assess';

    // Skill matrix & gap analysis
    case ViewSkillMatrix = 'skill-matrix.view';
    case ViewCompetencyGap = 'competency-gap.view';

    // Training
    case ViewTraining = 'training.view';
    case ManageTraining = 'training.manage';
    case ManageTrainingRecords = 'training-records.manage';

    // Certificates
    case ViewCertificates = 'certificates.view';
    case ManageCertificates = 'certificates.manage';

    // Development plans
    case ViewDevelopmentPlans = 'development-plans.view';
    case ManageDevelopmentPlans = 'development-plans.manage';

    // Reports
    case ViewReports = 'reports.view';

    // Safety (LOTOTO, ...)
    case ViewSafety = 'safety.view';
    case ManageSafety = 'safety.manage';

    public static function all(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Human-readable label for checkbox grids (Roles & Permissions screen).
     */
    public function label(): string
    {
        return match ($this) {
            self::ManageUsers => 'Manage users',
            self::ManageSettings => 'Manage settings',
            self::ManageMasterData => 'Manage master data',
            self::ViewAllEmployees => 'View all employees',
            self::ViewSubordinateEmployees => 'View subordinate employees',
            self::ViewOwnEmployee => 'View own employee record',
            self::ManageEmployees => 'Manage employees',
            self::ViewJobDescriptions => 'View job descriptions',
            self::ManageJobDescriptions => 'Manage job descriptions',
            self::ManageSkills => 'Manage skills',
            self::AssessCompetencies => 'Assess competencies',
            self::ViewSkillMatrix => 'View skill matrix',
            self::ViewCompetencyGap => 'View competency gap analysis',
            self::ViewTraining => 'View training',
            self::ManageTraining => 'Manage training',
            self::ManageTrainingRecords => 'Manage training records',
            self::ViewCertificates => 'View certificates',
            self::ManageCertificates => 'Manage certificates',
            self::ViewDevelopmentPlans => 'View development plans',
            self::ManageDevelopmentPlans => 'Manage development plans',
            self::ViewReports => 'View reports',
            self::ViewSafety => 'View safety (LOTOTO)',
            self::ManageSafety => 'Manage safety (LOTOTO)',
        };
    }

    /**
     * Module grouping, used to render the Roles & Permissions checkbox grid
     * in the same sections as the doc-comment blocks above.
     */
    public function group(): string
    {
        return match ($this) {
            self::ManageUsers, self::ManageSettings, self::ManageMasterData => 'Users & System Administration',
            self::ViewAllEmployees, self::ViewSubordinateEmployees, self::ViewOwnEmployee, self::ManageEmployees => 'Employees',
            self::ViewJobDescriptions, self::ManageJobDescriptions => 'Job Descriptions',
            self::ManageSkills, self::AssessCompetencies => 'Skills & Competencies',
            self::ViewSkillMatrix, self::ViewCompetencyGap => 'Skill Matrix & Gap Analysis',
            self::ViewTraining, self::ManageTraining, self::ManageTrainingRecords => 'Training',
            self::ViewCertificates, self::ManageCertificates => 'Certificates',
            self::ViewDevelopmentPlans, self::ManageDevelopmentPlans => 'Development Plans',
            self::ViewReports => 'Reports',
            self::ViewSafety, self::ManageSafety => 'Safety',
        };
    }

    /**
     * Permissions grouped by module, in declaration order — used to render
     * the Roles & Permissions checkbox grid one section at a time.
     *
     * @return array<string, self[]>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            $groups[$case->group()][] = $case;
        }

        return $groups;
    }
}
