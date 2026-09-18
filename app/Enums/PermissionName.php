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
}
