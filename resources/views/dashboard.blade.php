<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="space-y-8">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Employees</h2>
            <p class="mt-1 text-sm text-gray-500">Live counts from the Employee Database module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat label="Total Maintenance Employees" :value="$employeeSummary['total']" />
                <x-dashboard-stat label="Active Employees" :value="$employeeSummary['active']" />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Skills &amp; Competency</h2>
            <p class="mt-1 text-sm text-gray-500">Live counts from the Skills &amp; Competency Management module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat label="Total Skills Tracked" :value="$skillSummary['total_skills']" />
                <x-dashboard-stat label="Average Competency Score" :value="$skillSummary['average_competency_score'] ?? 'No assessments yet'" />
                <x-dashboard-stat label="Employees with Competency Gaps" :value="$skillSummary['employees_with_gaps']" />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Training &amp; Development</h2>
            <p class="mt-1 text-sm text-gray-500">Live counts from the Training Management module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat label="Active Training Programs" :value="$trainingSummary['programs']" />
                <x-dashboard-stat label="Upcoming Training Sessions" :value="$trainingSummary['upcoming_sessions']" />
                <x-dashboard-stat label="Completed Training Sessions" :value="$trainingSummary['completed_sessions']" />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Certificates</h2>
            <p class="mt-1 text-sm text-gray-500">Live counts from the Certificate Management module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat label="Certificates Expiring Soon" :value="$certificateSummary['expiring_soon']" />
                <x-dashboard-stat label="Expired Certificates" :value="$certificateSummary['expired']" />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Organization Overview</h2>
            <p class="mt-1 text-sm text-gray-500">Live counts from the Organization &amp; Master Data module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat label="Maintenance Departments" :value="$orgSummary['departments']" />
                <x-dashboard-stat label="Maintenance Areas" :value="$orgSummary['maintenance_areas']" />
                <x-dashboard-stat label="Maintenance Teams" :value="$orgSummary['maintenance_teams']" />
                <x-dashboard-stat label="Positions" :value="$orgSummary['positions']" />
            </div>
        </div>
    </div>
</x-app-layout>
