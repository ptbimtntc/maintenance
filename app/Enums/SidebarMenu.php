<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Every sidebar item an Administrator can show or hide per user (see
 * UserHiddenMenu). Separate from MenuKey, which governs edit rights: this
 * is purely visibility, so menus still under development can be kept away
 * from employees. Absence of a row means the menu is visible.
 */
enum SidebarMenu: string
{
    case Tasks = 'tasks';
    case ShiftComm = 'shift-comm';
    case Employees = 'employees';
    case OrganizationChart = 'organization-chart';
    case QrCodes = 'qr-codes';
    case Organization = 'organization';
    case Overtime = 'overtime';
    case JobDescriptions = 'job-descriptions';
    case Skills = 'skills';
    case SkillMatrix = 'skill-matrix';
    case CompetencyGap = 'competency-gap';
    case TrainingManagement = 'training-management';
    case TrainingCalendar = 'training-calendar';
    case TrainingRecords = 'training-records';
    case Certificates = 'certificates';
    case Recertification = 'recertification';
    case Signatories = 'signatories';
    case DevelopmentPlans = 'development-plans';
    case Lototo = 'lototo';
    case Reports = 'reports';
    case News = 'news';

    public function label(): string
    {
        return match ($this) {
            self::Tasks => 'My Task',
            self::ShiftComm => 'Shift Comm',
            self::Employees => 'Employees',
            self::OrganizationChart => 'Organization Chart',
            self::QrCodes => 'QR Codes',
            self::Organization => 'Organization',
            self::Overtime => 'Overtime',
            self::JobDescriptions => 'Job Descriptions',
            self::Skills => 'Skills & Competencies',
            self::SkillMatrix => 'Skill Matrix',
            self::CompetencyGap => 'Competency Gap Analysis',
            self::TrainingManagement => 'Training Management',
            self::TrainingCalendar => 'Training Calendar',
            self::TrainingRecords => 'Training Records',
            self::Certificates => 'Certificates',
            self::Recertification => 'Recertification',
            self::Signatories => 'Signatories',
            self::DevelopmentPlans => 'Development Plans',
            self::Lototo => 'LOTOTO',
            self::Reports => 'Reports',
            self::News => 'News',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Tasks, self::ShiftComm => 'Task',
            self::Employees, self::OrganizationChart, self::QrCodes, self::Organization, self::Overtime => 'People & Organization',
            self::JobDescriptions, self::Skills, self::SkillMatrix, self::CompetencyGap => 'Competency',
            self::TrainingManagement, self::TrainingCalendar, self::TrainingRecords, self::Certificates,
            self::Recertification, self::Signatories, self::DevelopmentPlans => 'Training & Development',
            self::Lototo => 'Safety',
            self::Reports, self::News => 'Insights & System',
        };
    }

    /**
     * Route-name patterns (Str::is) that belong to this menu, so a hidden
     * menu can't be reached by typing its URL either. Deliberately explicit:
     * e.g. employees.certificates.* belongs to Certificates, not Employees.
     *
     * @return string[]
     */
    public function routePatterns(): array
    {
        return match ($this) {
            self::Tasks => ['tasks.*'],
            self::ShiftComm => ['shift-comm.*'],
            self::Employees => ['employees.index', 'employees.create', 'employees.store', 'employees.show', 'employees.edit',
                'employees.update', 'employees.destroy', 'employees.bulk-destroy', 'employees.import'],
            self::OrganizationChart => ['organization-chart.*'],
            self::QrCodes => ['employees.qr-codes'],
            self::Organization => ['organization.*'],
            self::Overtime => ['overtime.*'],
            self::JobDescriptions => ['job-descriptions.*'],
            self::Skills => ['skills.*'],
            self::SkillMatrix => ['skill-matrix.*'],
            self::CompetencyGap => ['competency-gap-analysis.*'],
            self::TrainingManagement => ['training.programs.*', 'training.sessions.show', 'training.sessions.edit',
                'training.sessions.update', 'training.sessions.destroy', 'training.sessions.participants.*'],
            self::TrainingCalendar => ['training.calendar'],
            self::TrainingRecords => ['training.records.*'],
            self::Certificates => ['certificates.index', 'certificates.import'],
            self::Recertification => ['certificates.recertification'],
            self::Signatories => ['signatories.*'],
            self::DevelopmentPlans => ['development-plans.*'],
            self::Lototo => ['safety.lototo.*'],
            self::Reports => ['reports.*'],
            self::News => ['news.*'],
        };
    }

    public static function forRoute(?string $routeName): ?self
    {
        if ($routeName === null) {
            return null;
        }

        foreach (self::cases() as $menu) {
            if (Str::is($menu->routePatterns(), $routeName)) {
                return $menu;
            }
        }

        return null;
    }

    /**
     * @return array<string, self[]> menus keyed by sidebar group, in order
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $menu) {
            $groups[$menu->group()][] = $menu;
        }

        return $groups;
    }
}
