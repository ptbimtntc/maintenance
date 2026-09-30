<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #3D3934; background: #FBFBFA; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #EBE9E5; border-radius: 8px; overflow: hidden; }
        .header { background: #F04D1B; color: #ffffff; padding: 20px 24px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 4px 0 0; font-size: 12px; opacity: .9; }
        .section { padding: 20px 24px; border-top: 1px solid #EBE9E5; }
        .section h2 { margin: 0 0 10px; font-size: 14px; color: #A3300E; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        td, th { padding: 6px 0; text-align: left; vertical-align: top; }
        .muted { color: #7A7267; font-size: 12px; }
        .footer { padding: 16px 24px; font-size: 11px; color: #999087; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: bold; }
        .badge-danger { background: #FBE2DD; color: #862B1C; }
        .badge-warning { background: #FFEFD1; color: #A35804; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Weekly Team Summary</h1>
            <p>For {{ $supervisor->full_name }} &middot; {{ now()->format('d M Y') }}</p>
        </div>

        <div class="section">
            <h2>Expired Certificates ({{ $expiredCertificates->count() }})</h2>
            @if ($expiredCertificates->isEmpty())
                <p class="muted">None of your team's certificates are expired.</p>
            @else
                <table>
                    @foreach ($expiredCertificates as $certificate)
                        <tr>
                            <td>{{ $certificate->employee->full_name }} &mdash; {{ $certificate->name }}</td>
                            <td><span class="badge badge-danger">Expired {{ $certificate->expiry_date->format('d M Y') }}</span></td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="section">
            <h2>Certificates Expiring Soon ({{ $expiringCertificates->count() }})</h2>
            @if ($expiringCertificates->isEmpty())
                <p class="muted">No upcoming certificate expirations for your team.</p>
            @else
                <table>
                    @foreach ($expiringCertificates as $certificate)
                        <tr>
                            <td>{{ $certificate->employee->full_name }} &mdash; {{ $certificate->name }}</td>
                            <td><span class="badge badge-warning">Expires {{ $certificate->expiry_date->format('d M Y') }}</span></td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="section">
            <h2>Upcoming Training Sessions ({{ $upcomingSessions->count() }})</h2>
            @if ($upcomingSessions->isEmpty())
                <p class="muted">No training sessions scheduled for your team in the next 7 days.</p>
            @else
                <table>
                    @foreach ($upcomingSessions as $session)
                        <tr>
                            <td>{{ $session->trainingProgram->title }}</td>
                            <td>{{ $session->start_date->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="section">
            <h2>Skill Gaps to Follow Up ({{ $employeesWithSkillGaps->count() }})</h2>
            @if ($employeesWithSkillGaps->isEmpty())
                <p class="muted">No open skill gaps for your team.</p>
            @else
                <table>
                    @foreach ($employeesWithSkillGaps as $employee)
                        <tr>
                            <td>{{ $employee->full_name }}</td>
                            <td class="muted">{{ $employee->position?->title }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="footer">
            This is an automated weekly summary from the Maintenance People Development System. Log in to the dashboard for full details.
        </div>
    </div>
</body>
</html>
