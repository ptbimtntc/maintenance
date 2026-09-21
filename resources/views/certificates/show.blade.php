<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - {{ $certificate->employee->full_name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f6; min-height: 100vh; color: #212529; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.5; }
        .actions { max-width: 1000px; margin: 20px auto; padding: 0 20px; display: flex; justify-content: space-between; gap: 10px; }
        .btn { display: inline-block; padding: 6px 14px; border-radius: 6px; font-size: 14px; text-decoration: none; cursor: pointer; border: 1px solid #063875; }
        .btn-outline { color: #063875; background: transparent; }
        .btn-primary { color: #fff; background: #063875; }
        .wrapper { padding: 20px; }
        .certificate { width: 900px; max-width: 100%; min-height: 635px; margin: 0 auto; background: #fff; position: relative; overflow: hidden; border: 1px solid #d5dce5; box-shadow: 0 8px 30px rgba(0,0,0,.12); }
        .top { height: 75px; display: flex; align-items: center; justify-content: space-between; padding: 0 45px; border-bottom: 2px solid #e4e8ed; }
        .top img { height: 28px; width: auto; display: block; }
        .label { font-size: 11px; letter-spacing: 2px; color: #6c7785; }
        .content { text-align: center; padding: 35px 70px 85px; }
        .title { font-size: 33px; font-weight: bold; color: #122b56; letter-spacing: 1px; }
        .subtitle { font-size: 27px; font-weight: bold; color: #122b56; letter-spacing: 2px; margin-top: -2px; }
        .intro { margin: 22px 0 8px; font-size: 13px; color: #525d6b; }
        .name { font-size: 27px; font-weight: bold; color: #122b56; margin-top: 8px; }
        .nik { margin-top: 5px; font-size: 12px; color: #555f6c; }
        .description { margin: 25px auto 8px; max-width: 600px; font-size: 12px; line-height: 1.6; color: #444c57; }
        .competency { font-size: 20px; font-weight: bold; color: #123d78; letter-spacing: 1px; margin-top: 8px; text-transform: uppercase; }
        .score { margin-top: 8px; font-size: 12px; color: #444; }
        .score strong { color: #142e57; font-size: 14px; }
        .authorization { max-width: 600px; margin: 16px auto 0; font-size: 11px; line-height: 1.6; color: #505965; }
        .signature-area { display: flex; align-items: flex-end; justify-content: center; gap: 80px; margin-top: 25px; }
        .signature { width: 190px; text-align: center; font-size: 11px; }
        .signature-space { height: 40px; }
        .signature-line { border-top: 1px solid #27313f; margin-bottom: 7px; }
        .signature strong { display: block; font-size: 11px; color: #293342; min-height: 16px; }
        .signature span { display: block; margin-top: 3px; font-size: 9px; color: #68717d; }
        .stamp { width: 75px; height: 75px; border-radius: 50%; border: 5px double #c79b32; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 12px; font-weight: bold; color: #a47b1f; transform: rotate(-8deg); }
        .footer { position: absolute; bottom: 0; left: 0; right: 0; height: 65px; display: flex; justify-content: space-around; align-items: center; background: #063875; color: #fff; }
        .footer > div { min-width: 30%; text-align: center; border-right: 1px solid rgba(255,255,255,.25); }
        .footer > div:last-child { border-right: none; }
        .footer span { display: block; font-size: 9px; opacity: .8; margin-bottom: 4px; }
        .footer strong { display: block; font-size: 11px; }
        .unavailable { max-width: 520px; margin: 60px auto; background: #fff; border: 1px solid #d5dce5; border-radius: 8px; padding: 30px; text-align: center; }
        @media (max-width: 768px) {
            .wrapper { padding: 10px; }
            .certificate { min-height: 650px; }
            .top { padding: 0 20px; }
            .content { padding: 30px 25px 85px; }
            .title { font-size: 26px; } .subtitle { font-size: 21px; } .name { font-size: 23px; }
            .signature-area { gap: 20px; } .signature { width: 130px; }
            .stamp { width: 60px; height: 60px; font-size: 10px; }
        }
        @media print {
            @page { size: A4 landscape; margin: 0; }
            body { background: #fff; }
            .actions { display: none !important; }
            .wrapper { padding: 0; }
            .certificate { width: 100%; max-width: none; min-height: 100vh; border: none; box-shadow: none; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ route('employees.show', ['employee' => $certificate->employee, 'tab' => 'certificates']) }}" class="btn btn-outline">&larr; Back</a>
        @if ($available)
            <button onclick="window.print()" class="btn btn-primary">Print / Save PDF</button>
        @endif
    </div>

    @if (! $available)
        <div class="unavailable">
            <img src="{{ asset('images/Bekaert_logo_pos_RGB.png') }}" alt="Bekaert" style="height:28px;margin-bottom:16px">
            <p style="margin:0">This certificate isn't available yet. It needs to be verified and have a certificate number first.</p>
        </div>
    @else
        <div class="wrapper">
            <div class="certificate">
                <div class="top">
                    <img src="{{ asset('images/Bekaert_logo_pos_RGB.png') }}" alt="Bekaert">
                    <div class="label">EMPLOYEE COMPETENCY</div>
                </div>

                <div class="content">
                    <div class="title">CERTIFICATE</div>
                    <div class="subtitle">OF COMPETENCY</div>

                    <p class="intro">This is to certify that</p>
                    <div class="name">{{ $certificate->employee->full_name }}</div>
                    <div class="nik">NIK : {{ $certificate->employee->employee_number }}</div>

                    <p class="description">has successfully completed the training and assessment for</p>
                    <div class="competency">{{ $certificate->name }}</div>

                    @if ($score !== null)
                        <div class="score">with a score of <strong>{{ rtrim(rtrim(number_format((float) $score, 2), '0'), '.') }} / 100</strong></div>
                    @endif

                    <p class="authorization">and is authorized to perform basic work in accordance with company procedures and safety standards.</p>

                    <div class="signature-area">
                        <div class="signature">
                            <div class="signature-space"></div>
                            <div class="signature-line"></div>
                            <strong>{{ $certificate->trainer_name }}</strong>
                            <span>Trainer</span>
                        </div>

                        <div class="stamp">PASSED</div>

                        <div class="signature">
                            <div class="signature-space"></div>
                            <div class="signature-line"></div>
                            <strong>{{ $certificate->authorizer_name ?: ($certificate->authorizer_title ?: 'Maintenance Manager') }}</strong>
                            <span>{{ $certificate->authorizer_name ? ($certificate->authorizer_title ?: 'Maintenance Manager') : 'PT Bekaert Indonesia' }}</span>
                        </div>
                    </div>
                </div>

                <div class="footer">
                    <div><span>Certificate No.</span><strong>{{ $certificate->certificate_number }}</strong></div>
                    <div><span>Training Date</span><strong>{{ $certificate->issue_date?->format('d M Y') ?? '-' }}</strong></div>
                    <div><span>Valid Until</span><strong>{{ $certificate->expiry_date?->format('d M Y') ?? 'No Expiry' }}</strong></div>
                </div>
            </div>
        </div>
    @endif
</body>
</html>
