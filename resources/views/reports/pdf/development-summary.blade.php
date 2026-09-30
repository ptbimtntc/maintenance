<x-reports.pdf-layout title="Employee Development Summary">
    <table>
        <tbody>
            <tr><td><strong>Total Development Plans</strong></td><td>{{ $totalPlans }}</td></tr>
            <tr><td><strong>Overdue (past target date)</strong></td><td>{{ $overdue }}</td></tr>
        </tbody>
    </table>

    <h2>By Status</h2>
    <table>
        <tbody>
            @forelse ($byStatus as $status => $count)
                <tr><td>{{ ucwords(str_replace('_', ' ', $status)) }}</td><td>{{ $count }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">No plans yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>By Development Action</h2>
    <table>
        <tbody>
            @forelse ($byAction as $action => $count)
                <tr><td>{{ \App\Models\EmployeeDevelopmentPlan::ACTIONS[$action] ?? $action }}</td><td>{{ $count }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">No plans yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>By Priority</h2>
    <table>
        <tbody>
            @forelse ($byPriority as $priority => $count)
                <tr><td>{{ ucfirst($priority) }}</td><td>{{ $count }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">No plans yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-reports.pdf-layout>
