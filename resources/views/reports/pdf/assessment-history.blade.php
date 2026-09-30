<x-reports.pdf-layout title="Competency Assessment History">
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Skill</th>
                <th>Level</th>
                <th>Date</th>
                <th>Assessed By</th>
                <th>Method</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assessments as $assessment)
                <tr>
                    <td>{{ $assessment->employee->full_name }}</td>
                    <td>{{ $assessment->skill->name }}</td>
                    <td>{{ $assessment->competencyLevel->level_number }} - {{ $assessment->competencyLevel->name }}</td>
                    <td>{{ $assessment->assessment_date->format('d M Y') }}</td>
                    <td>{{ $assessment->assessedBy?->name ?? '-' }}</td>
                    <td>{{ $assessment->assessment_method ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No assessments recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-reports.pdf-layout>
