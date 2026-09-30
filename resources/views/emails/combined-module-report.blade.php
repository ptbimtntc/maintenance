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
        .muted { color: #7A7267; font-size: 12px; }
        .footer { padding: 16px 24px; font-size: 11px; color: #999087; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Combined Report</h1>
            <p>{{ $periodStart->format('F Y') }}</p>
        </div>

        <div class="section">
            <p>Attached is the combined Training, Overtime, and Certificate report for {{ $periodStart->format('F Y') }}, one sheet per module.</p>
            <p class="muted">
                Training and Overtime cover records dated between {{ $periodStart->format('d M Y') }} and {{ $periodEnd->format('d M Y') }}.
                Certificates reflect what is currently expiring soon or already expired, as of today.
            </p>
        </div>

        <div class="footer">
            This is an automated monthly report. Recipients are everyone with "View reports" permission -
            manage this in Settings &rarr; Roles.
        </div>
    </div>
</body>
</html>
