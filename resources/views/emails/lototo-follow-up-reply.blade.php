{{-- FOLLOW UP LOTOTO: sent to the employee when the supervisor has written a follow-up on the employee's activity. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FOLLOW UP LOTOTO</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f4;font-family:Arial,Helvetica,sans-serif;color:#1c1917;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f4;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e7e5e4;border-radius:14px;overflow:hidden;">
                <tr>
                    <td style="background:#1c1917;border-left:6px solid #facc15;padding:22px 24px;">
                        <p style="margin:0;font-size:12px;font-weight:bold;letter-spacing:2px;color:#fde047;text-transform:uppercase;">Keselamatan Kerja</p>
                        <h1 style="margin:6px 0 0;font-size:24px;font-weight:900;color:#ffffff;">FOLLOW UP LOTOTO</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px 0;font-size:14px;color:#44403c;">
                        <p style="margin:0;">Kepada {{ $employee->full_name }},</p>
                        <p style="margin:8px 0 0;">{{ $supervisorName ?: 'Supervisor Anda' }} memberikan tindak lanjut atas aktivitas LOTOTO berikut.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 24px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                            @foreach (['Nomor LOTOTO' => $employee->lototo_number, 'Lokasi/Mesin' => $activity->location, 'Aktivitas' => $activity->activity] as $label => $value)
                                <tr>
                                    <td style="width:130px;padding:6px 0;vertical-align:top;color:#78716c;font-weight:bold;">{{ $label }}</td>
                                    <td style="padding:6px 0;vertical-align:top;font-weight:bold;color:#1c1917;">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 24px 0;">
                        <p style="margin:0 0 6px;font-size:13px;font-weight:bold;color:#78716c;">Follow Up dari supervisor</p>
                        <p style="margin:0;padding:12px 14px;border-radius:10px;background:#fef3c7;border:1px solid #fcd34d;color:#78350f;font-size:15px;line-height:1.5;white-space:pre-line;">{{ $followUp }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 24px 24px;">
                        <a href="{{ $link }}" style="display:inline-block;padding:14px 28px;border-radius:10px;background:#1c1917;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">LIHAT AKTIVITAS LOTOTO</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
