<x-reports.pdf-layout title="Training Hours Report">
    <h2>By Employee</h2>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Total Hours</th>
                <th>Completed Trainings</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->full_name }}</td>
                    <td>{{ $row->training_records_sum_duration_hours }}</td>
                    <td>{{ $row->completed_trainings_count }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No training hours recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>By Department</h2>
    <table>
        <thead>
            <tr>
                <th>Department</th>
                <th>Total Hours</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byDepartment as $department => $hours)
                <tr>
                    <td>{{ $department }}</td>
                    <td>{{ $hours }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">No training hours recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-reports.pdf-layout>
