<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Division;
use App\Models\EmploymentSource;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\Location;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use App\Models\Shift;
use App\Models\SkillPosition;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Sample organisation master data for local development and demos.
     * This is clearly fictional sample data, not real PT Bekaert Indonesia data.
     */
    public function run(): void
    {
        $maintenanceDept = Department::firstOrCreate(
            ['code' => 'MTC'],
            ['name' => 'Maintenance Department', 'description' => 'Sample department for demo purposes.']
        );

        $productionDept = Department::firstOrCreate(
            ['code' => 'PRD'],
            ['name' => 'Production Department', 'description' => 'Sample department for demo purposes.']
        );

        Division::firstOrCreate(
            ['code' => 'MTC-ENG'],
            ['department_id' => $maintenanceDept->id, 'name' => 'Maintenance Engineering', 'description' => 'Sample division.']
        );

        Division::firstOrCreate(
            ['code' => 'PRD-WIRE'],
            ['department_id' => $productionDept->id, 'name' => 'Wire Production', 'description' => 'Sample division.']
        );

        $areas = [
            ['code' => 'AREA-DRW', 'name' => 'Wire Drawing Area'],
            ['code' => 'AREA-GLV', 'name' => 'Galvanizing Area'],
            ['code' => 'AREA-UTL', 'name' => 'Utility & Facility'],
            ['code' => 'AREA-WKS', 'name' => 'Central Workshop'],
        ];

        $areaModels = [];
        foreach ($areas as $area) {
            $areaModels[$area['code']] = MaintenanceArea::firstOrCreate(
                ['code' => $area['code']],
                [
                    'department_id' => $maintenanceDept->id,
                    'name' => $area['name'],
                    'description' => 'Sample maintenance area for demo purposes.',
                ]
            );
        }

        $teams = [
            ['code' => 'TEAM-MECH', 'name' => 'Mechanical Team', 'area' => 'AREA-WKS'],
            ['code' => 'TEAM-ELEC', 'name' => 'Electrical Team', 'area' => 'AREA-WKS'],
            ['code' => 'TEAM-UTL', 'name' => 'Utility Team', 'area' => 'AREA-UTL'],
            ['code' => 'TEAM-DRW', 'name' => 'Drawing Line Team', 'area' => 'AREA-DRW'],
        ];

        foreach ($teams as $team) {
            MaintenanceTeam::firstOrCreate(
                ['code' => $team['code']],
                [
                    'maintenance_area_id' => $areaModels[$team['area']]->id,
                    'name' => $team['name'],
                    'description' => 'Sample maintenance team for demo purposes.',
                ]
            );
        }

        $positions = [
            ['code' => 'POS-TECH', 'title' => 'Maintenance Technician', 'grade' => 1],
            ['code' => 'POS-STECH', 'title' => 'Senior Maintenance Technician', 'grade' => 2],
            ['code' => 'POS-SPV', 'title' => 'Maintenance Supervisor', 'grade' => 3],
            ['code' => 'POS-ENG', 'title' => 'Maintenance Engineer', 'grade' => 3],
            ['code' => 'POS-MGR', 'title' => 'Maintenance Manager', 'grade' => 4],
        ];

        foreach ($positions as $position) {
            Position::firstOrCreate(
                ['code' => $position['code']],
                [
                    'department_id' => $maintenanceDept->id,
                    'title' => $position['title'],
                    'grade_level' => $position['grade'],
                    'description' => 'Sample position for demo purposes.',
                ]
            );
        }

        $employmentTypes = [
            ['code' => 'PERM', 'name' => 'Permanent'],
            ['code' => 'CONT', 'name' => 'Contract'],
            ['code' => 'OUTS', 'name' => 'Outsourced'],
            ['code' => 'APPR', 'name' => 'Apprentice'],
        ];

        foreach ($employmentTypes as $type) {
            EmploymentType::firstOrCreate(['code' => $type['code']], ['name' => $type['name']]);
        }

        $employmentStatuses = [
            ['code' => 'ACTIVE', 'name' => 'Active', 'counts_as_active' => true],
            ['code' => 'LEAVE', 'name' => 'On Leave', 'counts_as_active' => true],
            ['code' => 'RESIGNED', 'name' => 'Resigned', 'counts_as_active' => false],
            ['code' => 'TERMINATED', 'name' => 'Terminated', 'counts_as_active' => false],
            ['code' => 'RETIRED', 'name' => 'Retired', 'counts_as_active' => false],
        ];

        foreach ($employmentStatuses as $status) {
            EmploymentStatus::firstOrCreate(
                ['code' => $status['code']],
                ['name' => $status['name'], 'counts_as_active' => $status['counts_as_active']]
            );
        }

        $shifts = [
            ['code' => 'DAY', 'name' => 'Day (Non-Shift)', 'start' => '08:00', 'end' => '17:00'],
            ['code' => 'SHIFT-A', 'name' => 'Shift A', 'start' => '06:00', 'end' => '14:00'],
            ['code' => 'SHIFT-B', 'name' => 'Shift B', 'start' => '14:00', 'end' => '22:00'],
            ['code' => 'SHIFT-C', 'name' => 'Shift C', 'start' => '22:00', 'end' => '06:00'],
        ];

        foreach ($shifts as $shift) {
            Shift::firstOrCreate(
                ['code' => $shift['code']],
                ['name' => $shift['name'], 'start_time' => $shift['start'], 'end_time' => $shift['end']]
            );
        }

        $locations = [
            ['code' => 'PLANT-1', 'name' => 'Plant 1 - Main Factory'],
            ['code' => 'PLANT-2', 'name' => 'Plant 2 - Wire Mill'],
            ['code' => 'WORKSHOP', 'name' => 'Central Workshop Building'],
        ];

        foreach ($locations as $location) {
            Location::firstOrCreate(['code' => $location['code']], ['name' => $location['name']]);
        }

        $businessUnits = [
            ['code' => 'BU-TC', 'name' => 'Tire Cord'],
            ['code' => 'BU-HP', 'name' => 'Half Product'],
            ['code' => 'BU-DX', 'name' => 'Dramix'],
        ];

        foreach ($businessUnits as $unit) {
            BusinessUnit::firstOrCreate(['code' => $unit['code']], ['name' => $unit['name']]);
        }

        $skillPositions = [
            ['code' => 'SKP-MECH', 'name' => 'Mechanical'],
            ['code' => 'SKP-ELEC', 'name' => 'Electrical'],
            ['code' => 'SKP-INST', 'name' => 'Instrument'],
        ];

        foreach ($skillPositions as $skillPosition) {
            SkillPosition::firstOrCreate(['code' => $skillPosition['code']], ['name' => $skillPosition['name']]);
        }

        $employmentSources = [
            ['code' => 'SRC-BEK', 'name' => 'Bekaert'],
            ['code' => 'SRC-BRX', 'name' => 'Brexa'],
            ['code' => 'SRC-GOK', 'name' => 'Gokko'],
            ['code' => 'SRC-MHK', 'name' => 'Mahkota'],
            ['code' => 'SRC-TSS', 'name' => 'TSS'],
        ];

        foreach ($employmentSources as $source) {
            EmploymentSource::firstOrCreate(['code' => $source['code']], ['name' => $source['name']]);
        }
    }
}
