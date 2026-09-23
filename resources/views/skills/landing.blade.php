<x-app-layout>
    <x-slot name="header">Skills &amp; Competencies</x-slot>

    <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
        @can(\App\Enums\PermissionName::ManageSkills->value)
            <a href="{{ route('skills.positions.index') }}" class="rounded-md border border-l-4 border-neutral-200 border-l-brand-500 bg-white px-3 py-2 transition hover:border-brand-400 hover:border-l-brand-500 hover:bg-brand-50/40">
                <p class="text-sm font-medium text-neutral-800">Position Skill Requirements</p>
                <p class="text-xs text-neutral-500">Define which skills and competency levels each position requires.</p>
            </a>
        @endcan

        @can(\App\Enums\PermissionName::ManageMasterData->value)
            <a href="{{ route('organization.index', ['skills', 'from' => 'skills']) }}" class="rounded-md border border-l-4 border-neutral-200 border-l-brand-500 bg-white px-3 py-2 transition hover:border-brand-400 hover:border-l-brand-500 hover:bg-brand-50/40">
                <p class="text-sm font-medium text-neutral-800">Skill Catalog</p>
                <p class="text-xs text-neutral-500">{{ \App\Models\Skill::count() }} skill(s) defined across all categories.</p>
            </a>

            <a href="{{ route('organization.index', ['skill-categories', 'from' => 'skills']) }}" class="rounded-md border border-l-4 border-neutral-200 border-l-brand-500 bg-white px-3 py-2 transition hover:border-brand-400 hover:border-l-brand-500 hover:bg-brand-50/40">
                <p class="text-sm font-medium text-neutral-800">Skill Categories</p>
                <p class="text-xs text-neutral-500">{{ \App\Models\SkillCategory::count() }} categor(y/ies).</p>
            </a>

            <a href="{{ route('organization.index', ['competency-levels', 'from' => 'skills']) }}" class="rounded-md border border-l-4 border-neutral-200 border-l-brand-500 bg-white px-3 py-2 transition hover:border-brand-400 hover:border-l-brand-500 hover:bg-brand-50/40">
                <p class="text-sm font-medium text-neutral-800">Competency Levels</p>
                <p class="text-xs text-neutral-500">{{ \App\Models\CompetencyLevel::count() }} level(s) configured.</p>
            </a>
        @endcan

        @can(\App\Enums\PermissionName::ViewSkillMatrix->value)
            <a href="{{ route('skill-matrix.index') }}" class="rounded-md border border-l-4 border-neutral-200 border-l-accent-500 bg-white px-3 py-2 transition hover:border-brand-400 hover:border-l-brand-500 hover:bg-brand-50/40">
                <p class="text-sm font-medium text-neutral-800">Skill Matrix</p>
                <p class="text-xs text-neutral-500">Compare current vs. required competency across employees.</p>
            </a>
        @endcan

        @can(\App\Enums\PermissionName::ViewCompetencyGap->value)
            <a href="{{ route('competency-gap-analysis.index') }}" class="rounded-md border border-l-4 border-neutral-200 border-l-accent-500 bg-white px-3 py-2 transition hover:border-brand-400 hover:border-l-brand-500 hover:bg-brand-50/40">
                <p class="text-sm font-medium text-neutral-800">Competency Gap Analysis</p>
                <p class="text-xs text-neutral-500">Department-wide gap findings and training suggestions.</p>
            </a>
        @endcan
    </div>
</x-app-layout>
