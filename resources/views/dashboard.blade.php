@php
$canViewEmployees = auth()->user()->can('viewAny', \App\Models\Employee::class);
$canViewSkillMatrix = auth()->user()->can(\App\Enums\PermissionName::ViewSkillMatrix->value);
$canViewCompetencyGap = auth()->user()->can(\App\Enums\PermissionName::ViewCompetencyGap->value);
$canViewTraining = auth()->user()->can(\App\Enums\PermissionName::ViewTraining->value);
$canViewCertificates = auth()->user()->can(\App\Enums\PermissionName::ViewCertificates->value);
$canManageMasterData = auth()->user()->can(\App\Enums\PermissionName::ManageMasterData->value);

$icons = [
    'employees' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    'check' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'skills' => 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342',
    'skill-matrix' => 'M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5m7.5 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M12 10.875v2.25',
    'gap' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
    'training' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
    'calendar' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'records' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z',
    'certificate' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
    'warning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    'organization' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
];
@endphp

<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="space-y-10">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Employees</h2>
            <p class="mt-1 text-sm text-neutral-500">Live counts from the Employee Database module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat
                    label="Total Maintenance Employees"
                    :value="$employeeSummary['total']"
                    icon="{{ $icons['employees'] }}"
                    color="blue"
                    :href="$canViewEmployees ? route('employees.index') : null"
                />
                <x-dashboard-stat
                    label="Active Employees"
                    :value="$employeeSummary['active']"
                    icon="{{ $icons['check'] }}"
                    color="green"
                    :href="$canViewEmployees ? route('employees.index') : null"
                    chartType="doughnut"
                    :chartLabels="['Active', 'Inactive']"
                    :chartValues="[$employeeSummary['active'], $employeeSummary['inactive']]"
                    :chartColors="['#16a34a', '#e5e7eb']"
                />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Skills &amp; Competency</h2>
            <p class="mt-1 text-sm text-neutral-500">Live counts from the Skills &amp; Competency Management module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat
                    label="Total Skills Tracked"
                    :value="$skillSummary['total_skills']"
                    icon="{{ $icons['skills'] }}"
                    color="purple"
                    :href="route('skills.landing')"
                />
                <x-dashboard-stat
                    label="Average Competency Score"
                    :value="$skillSummary['average_competency_score'] ?? 'No assessments yet'"
                    icon="{{ $icons['skill-matrix'] }}"
                    color="blue"
                    :href="$canViewSkillMatrix ? route('skill-matrix.index') : null"
                />
                <x-dashboard-stat
                    label="Employees with Competency Gaps"
                    :value="$skillSummary['employees_with_gaps']"
                    icon="{{ $icons['gap'] }}"
                    color="red"
                    :href="$canViewCompetencyGap ? route('competency-gap-analysis.index') : null"
                    chartType="bar"
                    :chartLabels="['Meets', 'Gap', 'Incomplete', 'No Req.']"
                    :chartValues="[$skillSummary['breakdown']['meets'], $skillSummary['breakdown']['gap'], $skillSummary['breakdown']['incomplete'], $skillSummary['breakdown']['no_requirements']]"
                    :chartColors="['#16a34a', '#dc2626', '#f59e0b', '#9ca3af']"
                />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Training &amp; Development</h2>
            <p class="mt-1 text-sm text-neutral-500">Live counts from the Training Management module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat
                    label="Active Training Programs"
                    :value="$trainingSummary['programs']"
                    icon="{{ $icons['training'] }}"
                    color="blue"
                    :href="$canViewTraining ? route('training.programs.index') : null"
                />
                <x-dashboard-stat
                    label="Upcoming Training Sessions"
                    :value="$trainingSummary['upcoming_sessions']"
                    icon="{{ $icons['calendar'] }}"
                    color="amber"
                    :href="$canViewTraining ? route('training.calendar') : null"
                />
                <x-dashboard-stat
                    label="Completed Training Sessions"
                    :value="$trainingSummary['completed_sessions']"
                    icon="{{ $icons['records'] }}"
                    color="green"
                    :href="$canViewTraining ? route('training.records.index') : null"
                    chartType="bar"
                    :chartLabels="['Programs', 'Upcoming', 'Completed']"
                    :chartValues="[$trainingSummary['programs'], $trainingSummary['upcoming_sessions'], $trainingSummary['completed_sessions']]"
                    :chartColors="['#2563eb', '#f59e0b', '#16a34a']"
                />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Certificates</h2>
            <p class="mt-1 text-sm text-neutral-500">Live counts from the Certificate Management module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat
                    label="Certificates Expiring Soon"
                    :value="$certificateSummary['expiring_soon']"
                    icon="{{ $icons['warning'] }}"
                    color="amber"
                    :href="$canViewCertificates ? route('certificates.index', ['status' => 'expiring_soon']) : null"
                />
                <x-dashboard-stat
                    label="Expired Certificates"
                    :value="$certificateSummary['expired']"
                    icon="{{ $icons['certificate'] }}"
                    color="red"
                    :href="$canViewCertificates ? route('certificates.index', ['status' => 'expired']) : null"
                    chartType="doughnut"
                    :chartLabels="['Valid', 'Expiring Soon', 'Expired', 'Pending']"
                    :chartValues="[$certificateSummary['valid'], $certificateSummary['expiring_soon'], $certificateSummary['expired'], $certificateSummary['pending_verification']]"
                    :chartColors="['#16a34a', '#f59e0b', '#dc2626', '#3b82f6']"
                    :chartLegend="true"
                />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Organization Overview</h2>
            <p class="mt-1 text-sm text-neutral-500">Live counts from the Organization &amp; Master Data module.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-dashboard-stat
                    label="Maintenance Departments"
                    :value="$orgSummary['departments']"
                    icon="{{ $icons['organization'] }}"
                    color="slate"
                    :href="$canManageMasterData ? route('organization.index', 'departments') : null"
                />
                <x-dashboard-stat
                    label="Maintenance Areas"
                    :value="$orgSummary['maintenance_areas']"
                    icon="{{ $icons['organization'] }}"
                    color="slate"
                    :href="$canManageMasterData ? route('organization.index', 'maintenance-areas') : null"
                />
                <x-dashboard-stat
                    label="Maintenance Teams"
                    :value="$orgSummary['maintenance_teams']"
                    icon="{{ $icons['organization'] }}"
                    color="slate"
                    :href="$canManageMasterData ? route('organization.index', 'maintenance-teams') : null"
                />
                <x-dashboard-stat
                    label="Positions"
                    :value="$orgSummary['positions']"
                    icon="{{ $icons['organization'] }}"
                    color="slate"
                    :href="$canManageMasterData ? route('organization.index', 'positions') : null"
                    chartType="bar"
                    :chartLabels="['Depts', 'Areas', 'Teams', 'Positions']"
                    :chartValues="[$orgSummary['departments'], $orgSummary['maintenance_areas'], $orgSummary['maintenance_teams'], $orgSummary['positions']]"
                    :chartColors="['#64748b', '#64748b', '#64748b', '#64748b']"
                />
            </div>
        </div>
    </div>
</x-app-layout>
