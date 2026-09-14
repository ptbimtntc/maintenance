<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\Location;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use App\Models\Shift;

/**
 * Configuration-driven registry of the simple "lookup" tables managed under
 * the Organization section. Each entry describes enough about its model for
 * a single generic controller + set of views to provide full CRUD, instead
 * of duplicating near-identical index/create/edit/update code nine times.
 *
 * - name_field: the column used as the record's display name.
 * - parent: an optional single belongsTo relation shown as a dropdown.
 * - extra_fields: additional simple inputs beyond name/code/description/is_active.
 */
return [
    'departments' => [
        'model' => Department::class,
        'label' => 'Departments',
        'singular' => 'Department',
        'name_field' => 'name',
    ],

    'divisions' => [
        'model' => Division::class,
        'label' => 'Divisions',
        'singular' => 'Division',
        'name_field' => 'name',
        'parent' => [
            'field' => 'department_id',
            'model' => Department::class,
            'label' => 'Department',
        ],
    ],

    'maintenance-areas' => [
        'model' => MaintenanceArea::class,
        'label' => 'Maintenance Areas',
        'singular' => 'Maintenance Area',
        'name_field' => 'name',
        'parent' => [
            'field' => 'department_id',
            'model' => Department::class,
            'label' => 'Department',
        ],
    ],

    'maintenance-teams' => [
        'model' => MaintenanceTeam::class,
        'label' => 'Maintenance Teams',
        'singular' => 'Maintenance Team',
        'name_field' => 'name',
        'parent' => [
            'field' => 'maintenance_area_id',
            'model' => MaintenanceArea::class,
            'label' => 'Maintenance Area',
        ],
    ],

    'positions' => [
        'model' => Position::class,
        'label' => 'Positions',
        'singular' => 'Position',
        'name_field' => 'title',
        'parent' => [
            'field' => 'department_id',
            'model' => Department::class,
            'label' => 'Department',
        ],
        'extra_fields' => [
            'grade_level' => ['type' => 'number', 'label' => 'Grade Level'],
        ],
    ],

    'employment-types' => [
        'model' => EmploymentType::class,
        'label' => 'Employment Types',
        'singular' => 'Employment Type',
        'name_field' => 'name',
    ],

    'employment-statuses' => [
        'model' => EmploymentStatus::class,
        'label' => 'Employment Statuses',
        'singular' => 'Employment Status',
        'name_field' => 'name',
        'extra_fields' => [
            'counts_as_active' => ['type' => 'boolean', 'label' => 'Counts as Active Employment'],
        ],
    ],

    'shifts' => [
        'model' => Shift::class,
        'label' => 'Shifts',
        'singular' => 'Shift',
        'name_field' => 'name',
        'extra_fields' => [
            'start_time' => ['type' => 'time', 'label' => 'Start Time'],
            'end_time' => ['type' => 'time', 'label' => 'End Time'],
        ],
    ],

    'locations' => [
        'model' => Location::class,
        'label' => 'Locations',
        'singular' => 'Location',
        'name_field' => 'name',
    ],
];
