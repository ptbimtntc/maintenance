<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\EmploymentSource;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\Location;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use App\Models\Shift;
use App\Models\SkillPosition;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Sample fictional employees, built on top of the master data seeded by
     * MasterDataSeeder. A handful are linked to the demo login accounts so
     * "view own profile" / "view team" scoping has something real to show.
     */
    public function run(): void
    {
        $areas = MaintenanceArea::all();
        $teams = MaintenanceTeam::all();
        $positions = Position::all();
        $employmentTypes = EmploymentType::all();
        $activeStatus = EmploymentStatus::where('code', 'ACTIVE')->first();
        $locations = Location::all();
        $shifts = Shift::all();
        $businessUnits = BusinessUnit::all();
        $skillPositions = SkillPosition::all();
        $employmentSources = EmploymentSource::all();

        if ($areas->isEmpty() || $positions->isEmpty()) {
            return;
        }

        $technicianPosition = $positions->firstWhere('code', 'POS-TECH') ?? $positions->first();
        $supervisorPosition = $positions->firstWhere('code', 'POS-SPV') ?? $positions->first();
        $managerPosition = $positions->firstWhere('code', 'POS-MGR') ?? $positions->first();
        $workshopTeam = $teams->firstWhere('code', 'TEAM-MECH') ?? $teams->first();
        $workshopArea = $areas->firstWhere('code', 'AREA-WKS') ?? $areas->first();
        $tireCordUnit = $businessUnits->firstWhere('code', 'BU-TC') ?? $businessUnits->first();
        $bekaertSource = $employmentSources->firstWhere('code', 'SRC-BEK') ?? $employmentSources->first();
        $mechanicalSkillPosition = $skillPositions->firstWhere('code', 'SKP-MECH') ?? $skillPositions->first();

        // Link demo login accounts to real employee records.
        $demoLinks = [
            'manager@mpd.test' => ['position' => $managerPosition, 'team' => $workshopTeam, 'area' => $workshopArea, 'workforce_category' => 'WCM'],
            'supervisor@mpd.test' => ['position' => $supervisorPosition, 'team' => $workshopTeam, 'area' => $workshopArea, 'workforce_category' => 'WCM'],
            'staff@mpd.test' => ['position' => $technicianPosition, 'team' => $workshopTeam, 'area' => $workshopArea, 'workforce_category' => 'BC'],
        ];

        $demoEmployees = [];

        foreach ($demoLinks as $email => $link) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                continue;
            }

            $demoEmployees[$email] = Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_number' => 'EMP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'full_name' => $user->name,
                    'email' => $user->email,
                    'maintenance_area_id' => $link['area']?->id,
                    'maintenance_team_id' => $link['team']?->id,
                    'position_id' => $link['position']?->id,
                    'skill_position_id' => $mechanicalSkillPosition?->id,
                    'business_unit_id' => $tireCordUnit?->id,
                    'employment_type_id' => $employmentTypes->first()?->id,
                    'employment_source_id' => $bekaertSource?->id,
                    'workforce_category' => $link['workforce_category'],
                    'employment_status_id' => $activeStatus?->id,
                    'location_id' => $locations->first()?->id,
                    'shift_id' => $shifts->first()?->id,
                    'date_joined' => now()->subYears(3),
                ]
            );
        }

        // Wire up the reporting hierarchy for the demo accounts, so
        // "supervisor sees self + direct reports" has something real to
        // show: Manager <- Supervisor <- Staff.
        if (isset($demoEmployees['supervisor@mpd.test'], $demoEmployees['manager@mpd.test'])) {
            $demoEmployees['supervisor@mpd.test']->update(['supervisor_id' => $demoEmployees['manager@mpd.test']->id]);
        }
        if (isset($demoEmployees['staff@mpd.test'], $demoEmployees['supervisor@mpd.test'])) {
            $demoEmployees['staff@mpd.test']->update(['supervisor_id' => $demoEmployees['supervisor@mpd.test']->id]);
        }

        // A handful of additional fictional employees spread across the
        // seeded areas/teams/positions so lists, filters and the dashboard
        // have realistic-looking data to show. A few report to the demo
        // supervisor, so that account has more than one direct report.
        $additional = Employee::factory()
            ->count(15)
            ->state(fn () => [
                'maintenance_area_id' => $areas->random()->id,
                'maintenance_team_id' => $teams->random()->id,
                'position_id' => $positions->random()->id,
                'skill_position_id' => $skillPositions->isNotEmpty() ? $skillPositions->random()->id : null,
                'business_unit_id' => $businessUnits->isNotEmpty() ? $businessUnits->random()->id : null,
                'employment_type_id' => $employmentTypes->random()->id,
                'employment_source_id' => $employmentSources->isNotEmpty() ? $employmentSources->random()->id : null,
                'workforce_category' => fake()->randomElement(array_keys(Employee::WORKFORCE_CATEGORIES)),
                'employment_status_id' => EmploymentStatus::inRandomOrder()->first()?->id,
                'location_id' => $locations->random()->id,
                'shift_id' => $shifts->random()->id,
            ])
            ->create();

        if (isset($demoEmployees['supervisor@mpd.test'])) {
            $additional->take(2)->each(
                fn (Employee $employee) => $employee->update(['supervisor_id' => $demoEmployees['supervisor@mpd.test']->id])
            );
        }
    }
}
