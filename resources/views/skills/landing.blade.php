<x-app-layout>
    <x-slot name="header">Skills &amp; Competencies</x-slot>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @can(\App\Enums\PermissionName::ManageSkills->value)
            <a href="{{ route('skills.positions.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:border-slate-400 hover:shadow-sm">
                <p class="font-medium text-gray-900">Position Skill Requirements</p>
                <p class="mt-1 text-sm text-gray-500">Define which skills and competency levels each position requires.</p>
            </a>
        @endcan

        @can(\App\Enums\PermissionName::ManageMasterData->value)
            <a href="{{ route('organization.index', 'skills') }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:border-slate-400 hover:shadow-sm">
                <p class="font-medium text-gray-900">Skill Catalog</p>
                <p class="mt-1 text-sm text-gray-500">{{ \App\Models\Skill::count() }} skill(s) defined across all categories.</p>
            </a>

            <a href="{{ route('organization.index', 'skill-categories') }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:border-slate-400 hover:shadow-sm">
                <p class="font-medium text-gray-900">Skill Categories</p>
                <p class="mt-1 text-sm text-gray-500">{{ \App\Models\SkillCategory::count() }} categor(y/ies).</p>
            </a>

            <a href="{{ route('organization.index', 'competency-levels') }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:border-slate-400 hover:shadow-sm">
                <p class="font-medium text-gray-900">Competency Levels</p>
                <p class="mt-1 text-sm text-gray-500">{{ \App\Models\CompetencyLevel::count() }} level(s) configured.</p>
            </a>
        @endcan

        @can(\App\Enums\PermissionName::ViewSkillMatrix->value)
            <a href="{{ route('skill-matrix.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:border-slate-400 hover:shadow-sm">
                <p class="font-medium text-gray-900">Skill Matrix</p>
                <p class="mt-1 text-sm text-gray-500">Compare current vs. required competency across employees.</p>
            </a>
        @endcan

        @can(\App\Enums\PermissionName::ViewCompetencyGap->value)
            <a href="{{ route('competency-gap-analysis.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:border-slate-400 hover:shadow-sm">
                <p class="font-medium text-gray-900">Competency Gap Analysis</p>
                <p class="mt-1 text-sm text-gray-500">Department-wide gap findings and training suggestions.</p>
            </a>
        @endcan
    </div>
</x-app-layout>
