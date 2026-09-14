<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\MaintenanceArea;
use App\Models\MaintenanceTeam;
use App\Models\Position;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the Maintenance Department dashboard.
     *
     * Only modules that currently have real data behind them are queried.
     * Modules planned for later phases (skills, training, certificates) are
     * shown as an explicit "not yet available" state rather than fabricated
     * statistics.
     */
    public function index(): View
    {
        $orgSummary = [
            'departments' => Department::where('is_active', true)->count(),
            'maintenance_areas' => MaintenanceArea::where('is_active', true)->count(),
            'maintenance_teams' => MaintenanceTeam::where('is_active', true)->count(),
            'positions' => Position::where('is_active', true)->count(),
        ];

        $employeeSummary = [
            'total' => Employee::count(),
            'active' => Employee::active()->count(),
        ];

        $pendingModules = [
            'Total Skills Tracked',
            'Average Competency Score',
            'Employees with Competency Gaps',
            'Training Programs',
            'Upcoming Training Sessions',
            'Certificates Expiring Soon',
        ];

        return view('dashboard', [
            'orgSummary' => $orgSummary,
            'employeeSummary' => $employeeSummary,
            'pendingModules' => $pendingModules,
        ]);
    }
}
