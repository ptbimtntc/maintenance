<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <title>Employee Certificate Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f2f5f9; font-family: Arial, sans-serif; color: #17233c; }

        .sticky-stack { position: sticky; top: 0; z-index: 20; background: #fff; border-radius: 16px 16px 0 0; }
        .sticky-stack > .sticky-layer { position: static; top: auto; }

        .verification-card { max-width: 430px; margin: 20px auto; background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); }

        .verification-header { min-height: 190px; background: linear-gradient(rgba(4, 36, 83, 0.94), rgba(4, 36, 83, 0.94)); color: white; padding: 22px 20px; text-align: center; position: sticky; top: 0; z-index: 10; border-radius: 16px 16px 0 0; }
        .logo { text-align: left; }
        .logo .brand-logo { height: 28px; width: auto; display: block; filter: brightness(0) invert(1); }
        .verification-title { margin-top: 20px; font-size: 20px; font-weight: bold; line-height: 1.4; }

        .employee-profile { text-align: center; margin-top: -65px; position: sticky; top: 125px; z-index: 15; padding: 0 20px 20px; background: white; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06); }
        .employee-photo { width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 10px; overflow: hidden; border: 6px solid white; background: #e7ebef; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15); }
        .employee-photo img { width: 100%; height: 100%; object-fit: cover; }
        .photo-placeholder { width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; font-size: 42px; font-weight: bold; color: #17437e; }
        .employee-profile h2 { font-size: 25px; margin: 5px 0; }
        .employee-nik { color: #555; font-size: 14px; }
        .employee-position { margin-top: 5px; color: #555; font-size: 14px; }

        .status-valid { display: inline-flex; align-items: center; gap: 7px; margin-top: 15px; padding: 8px 16px; border-radius: 8px; background: #dff4df; color: #23833a; font-size: 13px; font-weight: bold; }
        .status-icon { width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #25a244; color: white; }

        .section-title { text-align: center; color: #174ca6; font-size: 16px; font-weight: bold; padding: 15px; border-bottom: 3px solid #2c63c7; position: sticky; top: 400px; z-index: 12; background: white; }

        .competency-list { padding: 12px; }
        .competency-item { position: relative; display: flex; align-items: flex-start; gap: 10px; padding: 14px 10px; margin-bottom: 10px; border: 1px solid #e2e6eb; border-radius: 10px; text-decoration: none; color: inherit; transition: 0.2s; }
        .competency-item:hover { background: #f8fbff; transform: translateY(-1px); }
        .competency-icon { width: 40px; height: 40px; flex-shrink: 0; border-radius: 7px; display: flex; align-items: center; justify-content: center; background: #e5f1ff; font-size: 22px; }
        .competency-content { flex: 1; min-width: 0; }
        .competency-name { font-size: 16px; font-weight: bold; margin-bottom: 8px; }
        .competency-info { font-size: 12px; color: #555; line-height: 1.7; }
        .competency-info strong { display: block; color: #222; font-weight: normal; }
        .competency-status { position: absolute; top: 12px; right: 10px; padding: 4px 8px; border-radius: 5px; font-size: 10px; font-weight: bold; }
        .competency-arrow { position: absolute; right: 8px; bottom: 12px; font-size: 22px; color: #777; }

        .status-pill-valid { background: #dff4df; color: #25863a; }
        .status-pill-warning { background: #fff2cc; color: #8a6a10; }
        .status-pill-danger { background: #fde2e1; color: #b42318; }
        .status-pill-secondary { background: #e8eaed; color: #5f6b7a; }
        .status-pill-info { background: #dbeafe; color: #1d4ed8; }
        .competency-icon.icon-info { background: #dbeafe; }
        .competency-icon.icon-danger { background: #fde2e1; }
        .competency-icon.icon-valid { background: #dff4df; }
        .competency-icon.icon-warning { background: #fff2cc; }

        .empty-state { padding: 30px 20px; text-align: center; color: #68717d; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="verification-card">
            <div class="sticky-stack">
                <div class="verification-header sticky-layer">
                    <div class="logo">
                        <img src="{{ asset('images/Bekaert_logo_pos_RGB.png') }}" alt="Bekaert" class="brand-logo">
                    </div>
                    <div class="verification-title">
                        Employee Competency<br>
                        Verification
                    </div>
                </div>

                <div class="employee-profile sticky-layer">
                    <div class="employee-photo">
                        @if ($employee->photo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}" alt="{{ $employee->full_name }}">
                        @else
                            <div class="photo-placeholder">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($employee->full_name, 0, 1)) }}</div>
                        @endif
                    </div>
                    <h2>{{ $employee->full_name }}</h2>
                    <div class="employee-nik">NIK : {{ $employee->employee_number }}</div>
                    <div class="employee-position">{{ $employee->department?->name ?? '-' }} - {{ $employee->position?->title ?? '-' }}</div>
                    <div class="status-valid">
                        <span class="status-icon">&#10003;</span>
                        STATUS: VERIFIED
                    </div>
                </div>

                <div class="section-title sticky-layer">SERTIFIKASI KOMPETENSI</div>
            </div>

            <div class="competency-list">
                @foreach ($pendingParticipations as $participant)
                    @php
                        $isFailed = $participant->quiz_submitted_at !== null;
                    @endphp
                    <a href="{{ route('verify.participant', $participant) }}" class="competency-item">
                        <div class="competency-icon {{ $isFailed ? 'icon-danger' : 'icon-info' }}">{{ $isFailed ? '✗' : '📅' }}</div>
                        <div class="competency-content">
                            <div class="competency-name">{{ $participant->trainingSession->trainingProgram->title }}</div>
                            <div class="competency-info">
                                <div>Scheduled Date <strong>{{ $participant->trainingSession->start_date->format('d M Y') }}</strong></div>
                                <div>Attendance <strong>{{ ucfirst($participant->attendance_status) }}</strong></div>
                                @if ($isFailed)
                                    <div>Score <strong>{{ $participant->quiz_score }} / 100</strong></div>
                                @endif
                            </div>
                        </div>
                        <div class="competency-status {{ $isFailed ? 'status-pill-danger' : 'status-pill-info' }}">{{ $isFailed ? 'Failed' : 'Assigned' }}</div>
                        <div class="competency-arrow">&rsaquo;</div>
                    </a>
                @endforeach

                @forelse ($certificates as $certificate)
                    @php
                        $status = $certificate->status();
                        $statusLabel = match ($status) {
                            'valid', 'no_expiry' => 'Valid',
                            'expiring_soon' => 'Expiring Soon',
                            'expired' => 'Expired',
                            default => 'Valid',
                        };
                        $statusClass = match ($status) {
                            'valid', 'no_expiry' => 'status-pill-valid',
                            'expiring_soon' => 'status-pill-warning',
                            'expired' => 'status-pill-danger',
                            default => 'status-pill-valid',
                        };
                        $statusIcon = match ($status) {
                            'valid', 'no_expiry' => '✓',
                            'expiring_soon' => '⚠',
                            'expired' => '✗',
                            default => '✓',
                        };
                        $iconClass = match ($status) {
                            'valid', 'no_expiry' => 'icon-valid',
                            'expiring_soon' => 'icon-warning',
                            'expired' => 'icon-danger',
                            default => 'icon-valid',
                        };
                    @endphp
                    <a href="{{ route('verify.certificate', $certificate) }}" target="_blank" class="competency-item">
                        <div class="competency-icon {{ $iconClass }}">{{ $statusIcon }}</div>
                        <div class="competency-content">
                            <div class="competency-name">{{ $certificate->name }}</div>
                            <div class="competency-info">
                                <div>Training Date <strong>{{ $certificate->issue_date?->format('d M Y') ?? '-' }}</strong></div>
                                <div>Expiry Date <strong>{{ $certificate->expiry_date?->format('d M Y') ?? 'No Expiry' }}</strong></div>
                                <div>Trainer <strong>{{ $certificate->trainer_name ?: '-' }}</strong></div>
                                <div>Certificate No. <strong>{{ $certificate->certificate_number ?: '-' }}</strong></div>
                            </div>
                        </div>
                        <div class="competency-status {{ $statusClass }}">{{ $statusLabel }}</div>
                        <div class="competency-arrow">&rsaquo;</div>
                    </a>
                @empty
                    @if ($pendingParticipations->isEmpty())
                        <div class="empty-state">No verified certificates found for this employee yet.</div>
                    @endif
                @endforelse
            </div>
        </div>
    </div>
</body>
</html>
