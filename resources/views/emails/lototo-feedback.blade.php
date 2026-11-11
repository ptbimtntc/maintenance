{{-- LOTOTO & LMRA FEEDBACK: sent to the technician's supervisor when the checklist holds a NO and/or the technician
     wrote feedback. Inline styles and tables only, so mail clients show it as designed. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LOTOTO &amp; LMRA FEEDBACK</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f4;font-family:Arial,Helvetica,sans-serif;color:#1c1917;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f4;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e7e5e4;border-radius:14px;overflow:hidden;">
                <tr>
                    <td style="background:#1c1917;border-left:6px solid #facc15;padding:22px 24px;">
                        <p style="margin:0;font-size:12px;font-weight:bold;letter-spacing:2px;color:#fde047;text-transform:uppercase;">Keselamatan Kerja</p>
                        <h1 style="margin:6px 0 0;font-size:24px;font-weight:900;color:#ffffff;">LOTOTO &amp; LMRA FEEDBACK</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px 0;">
                        <p style="margin:0;padding:10px 14px;border-radius:8px;background:#fef3c7;border:1px solid #fcd34d;color:#78350f;font-size:14px;font-weight:bold;">
                            Perlu feedback / follow-up supervisor
                        </p>
                        @if ($supervisor)
                            <p style="margin:12px 0 0;font-size:14px;color:#44403c;">Kepada {{ $supervisor->full_name }},</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 24px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                            @foreach ([
                                'Nama' => $employee->full_name,
                                'Nomor LOTOTO' => $employee->lototo_number,
                                'Lokasi/Mesin' => $activity->location,
                                'Aktivitas' => $activity->activity,
                            ] as $label => $value)
                                <tr>
                                    <td style="width:130px;padding:6px 0;vertical-align:top;color:#78716c;font-weight:bold;">{{ $label }}</td>
                                    <td style="padding:6px 0;vertical-align:top;font-weight:bold;color:#1c1917;">
                                        @if ($label === 'Nomor LOTOTO')
                                            <span style="display:inline-block;padding:2px 10px;border-radius:6px;background:#dc2626;color:#ffffff;font-weight:900;">{{ $value }}</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 24px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border:1px solid #e7e5e4;border-radius:10px;border-collapse:separate;overflow:hidden;">
                            @foreach ($checklist as $index => $item)
                                <tr>
                                    <td style="padding:9px 12px;border-top:{{ $index ? '1px solid #e7e5e4' : '0' }};{{ $item['yes'] ? '' : 'background:#fef2f2;color:#b91c1c;font-weight:bold;' }}">
                                        {{ $index + 1 }}. {{ $item['label'] }}
                                    </td>
                                    <td align="right" style="width:80px;padding:9px 12px;border-top:{{ $index ? '1px solid #e7e5e4' : '0' }};font-weight:900;{{ $item['yes'] ? 'color:#047857;' : 'background:#dc2626;color:#ffffff;' }}">
                                        {{ $item['yes'] ? 'YA' : 'TIDAK' }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 24px 0;">
                        <p style="margin:0;font-size:13px;font-weight:bold;color:#78716c;text-transform:uppercase;">Feedback dari teknisi</p>
                        <p style="margin:6px 0 0;padding:10px 12px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;font-size:14px;white-space:pre-line;">{{ filled($activity->supervisor_feedback) ? $activity->supervisor_feedback : '-' }}</p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:22px 24px;">
                        <a href="{{ $link }}" style="display:inline-block;padding:14px 28px;border-radius:10px;background:#1c1917;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">LINK TO FOLLOW UP</a>
                        <p style="margin:12px 0 0;font-size:12px;color:#78716c;">Link berlaku {{ \App\Support\LototoFollowUpLink::VALID_DAYS }} hari dan hanya untuk Anda sebagai supervisor karyawan ini.</p>
                        <p style="margin:6px 0 0;font-size:11px;color:#a8a29e;word-break:break-all;">{{ $link }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
