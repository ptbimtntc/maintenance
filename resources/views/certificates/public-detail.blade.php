<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competency Detail - {{ $certificate->employee->full_name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f2f5f9; font-family: Arial, sans-serif; color: #17233c; }

        .sticky-stack { position: sticky; top: 0; z-index: 20; background: #fff; border-radius: 12px 12px 0 0; }
        .sticky-stack > .sticky-layer { position: static; top: auto; }

        .detail-card { max-width: 700px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.07); }

        .detail-header { background: #063875; height: 58px; display: flex; align-items: center; padding: 0 20px; color: white; position: sticky; top: 0; z-index: 20; border-radius: 12px 12px 0 0; }
        .detail-header .brand-logo { height: 22px; width: auto; filter: brightness(0) invert(1); }

        .back-link { padding: 12px 20px 5px; }
        .back-link a { color: #2863bd; text-decoration: none; font-size: 13px; font-weight: 500; }

        .detail-profile { margin: 5px 14px 10px; padding: 15px; border: 1px solid #e0e5eb; border-radius: 9px; display: flex; align-items: center; gap: 18px; position: sticky; top: 58px; z-index: 15; background: white; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06); }
        .detail-photo { width: 105px; height: 105px; flex-shrink: 0; border-radius: 50%; overflow: hidden; background: #e8edf2; border: 4px solid white; box-shadow: 0 2px 8px rgba(0, 0, 0, .12); }
        .detail-photo img { width: 100%; height: 100%; object-fit: cover; }
        .detail-photo .photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 34px; font-weight: bold; color: #17437e; }
        .detail-employee-info h2 { margin: 0 0 5px; font-size: 23px; color: #17233c; }
        .detail-employee-info > div { margin-bottom: 4px; color: #333; font-size: 13px; }
        .detail-status { display: inline-block; margin-top: 6px; padding: 6px 12px; border-radius: 6px; font-weight: bold; }

        .detail-tabs { display: flex; margin: 0 14px; border-bottom: 1px solid #d9dfe7; position: sticky; top: 216px; z-index: 12; background: white; }
        .detail-tabs .tab { width: 50%; text-align: center; padding: 11px; font-size: 13px; font-weight: bold; color: #27334b; text-decoration: none; border-bottom: 4px solid transparent; }
        .detail-tabs .tab.active { color: #174ca6; border-bottom-color: #2863c8; }

        .information-section { margin: 0 14px; padding: 14px 16px; border-bottom: 1px solid #e4e7eb; }
        .information-section h3, .description-section h3 { font-size: 12px; color: #153f7d; margin-bottom: 10px; font-weight: bold; }
        .information-row { display: flex; font-size: 12px; line-height: 1.8; }
        .information-row span { width: 145px; color: #4e5663; }
        .information-row strong { flex: 1; color: #28303c; font-weight: 500; }

        .competency-detail-section { margin: 0 14px; padding: 15px 16px; border-bottom: 1px solid #e4e7eb; }
        .competency-detail-title { display: flex; align-items: center; gap: 10px; color: #173f7b; font-size: 13px; font-weight: bold; }
        .detail-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; background: #e4f2ff; font-size: 18px; }
        .detail-valid { margin-left: auto; padding: 4px 9px; border-radius: 5px; font-size: 10px; }

        .training-information { margin-top: 12px; }
        .training-row { display: flex; font-size: 12px; line-height: 1.8; }
        .training-row span { width: 145px; color: #4d5663; }
        .training-row strong { color: #202936; font-weight: 500; }

        .description-section { margin: 0 14px; padding: 15px 16px; border-bottom: 1px solid #e4e7eb; }
        .description-section p { margin: 0; font-size: 12px; line-height: 1.6; color: #333b47; }

        .note-box { margin: 15px 14px 20px; padding: 12px; border: 1px solid #d4e4f7; background: #f2f8ff; border-radius: 8px; display: flex; gap: 10px; color: #24528c; }
        .note-icon { font-size: 17px; }
        .note-box strong { font-size: 11px; }
        .note-box p { margin: 4px 0 0; font-size: 10px; line-height: 1.5; }

        .status-pill-valid { background: #dff4df; color: #25863a; }
        .status-pill-warning { background: #fff2cc; color: #8a6a10; }
        .status-pill-danger { background: #fde2e1; color: #b42318; }

        @media (max-width: 576px) {
            .detail-card { margin: 0; border-radius: 0; min-height: 100vh; }
            .detail-card .sticky-stack { border-radius: 0; }
            .detail-header { border-radius: 0; }
            .detail-profile { align-items: flex-start; }
            .detail-photo { width: 85px; height: 85px; }
            .detail-employee-info h2 { font-size: 19px; }
            .information-row span, .training-row span { width: 125px; }
        }
    </style>
</head>
<body>
    @php
        $employee = $certificate->employee;
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
        $description = $certificate->certificateType?->description ?: $certificate->relatedSkill?->description;
    @endphp
    <div class="container py-3">
        <div class="detail-card">
            <div class="sticky-stack">
                <div class="detail-header sticky-layer">
                    <img src="{{ asset('images/Bekaert_logo_pos_RGB.png') }}" alt="Bekaert" class="brand-logo">
                </div>

                <div class="back-link">
                    <a href="{{ route('verify.employee', $employee) }}">&larr; Kembali ke Ringkasan</a>
                </div>

                <div class="detail-profile sticky-layer">
                    <div class="detail-photo">
                        @if ($employee->photo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->photo_path) }}" alt="{{ $employee->full_name }}">
                        @else
                            <div class="photo-placeholder">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($employee->full_name, 0, 1)) }}</div>
                        @endif
                    </div>
                    <div class="detail-employee-info">
                        <h2>{{ $employee->full_name }}</h2>
                        <div>NIK : {{ $employee->employee_number }}</div>
                        <div>{{ $employee->department?->name ?? '-' }} - {{ $employee->position?->title ?? '-' }}</div>
                        <div class="detail-status {{ $statusClass }}">STATUS: {{ strtoupper($statusLabel) }}</div>
                    </div>
                </div>

                <div class="detail-tabs sticky-layer">
                    <div class="tab active">DETAIL</div>
                    @if ($certificate->certificate_number)
                        <a class="tab" href="{{ route('verify.certificate.print', $certificate) }}" target="_blank">SERTIFIKAT</a>
                    @else
                        <span class="tab" style="opacity:.5;cursor:not-allowed;">SERTIFIKAT</span>
                    @endif
                </div>
            </div>

            <div class="information-section">
                <h3>INFORMASI UMUM</h3>
                <div class="information-row"><span>Issue Date</span><strong>: {{ $certificate->issue_date?->format('d M Y') ?? '-' }}</strong></div>
                <div class="information-row"><span>Expiry Date</span><strong>: {{ $certificate->expiry_date?->format('d M Y') ?? 'No Expiry' }}</strong></div>
                <div class="information-row"><span>Department</span><strong>: {{ $employee->department?->name ?? '-' }}</strong></div>
                <div class="information-row"><span>Position</span><strong>: {{ $employee->position?->title ?? '-' }}</strong></div>
            </div>

            <div class="competency-detail-section">
                <div class="competency-detail-title">
                    <div class="detail-icon">&#9733;</div>
                    <div>KOMPETENSI: {{ \Illuminate\Support\Str::upper($certificate->name) }}</div>
                    <span class="detail-valid {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
                <div class="training-information">
                    <div class="training-row"><span>Status</span><strong>: {{ $statusLabel }}</strong></div>
                    @if ($score !== null)
                        <div class="training-row"><span>Score</span><strong>: {{ rtrim(rtrim(number_format((float) $score, 2), '0'), '.') }} / 100</strong></div>
                    @endif
                    <div class="training-row"><span>Training Date</span><strong>: {{ $certificate->issue_date?->format('d M Y') ?? '-' }}</strong></div>
                    <div class="training-row"><span>Expired Date</span><strong>: {{ $certificate->expiry_date?->format('d M Y') ?? 'No Expiry' }}</strong></div>
                    <div class="training-row"><span>Trainer</span><strong>: {{ $certificate->trainer_name ?: '-' }}</strong></div>
                    <div class="training-row"><span>Certificate No.</span><strong>: {{ $certificate->certificate_number ?: '-' }}</strong></div>
                    <div class="training-row"><span>Training Provider</span><strong>: {{ $certificate->issuing_organization ?: '-' }}</strong></div>
                </div>
            </div>

            @if ($description)
                <div class="description-section">
                    <h3>DESKRIPSI KOMPETENSI</h3>
                    <p>{{ $description }}</p>
                </div>
            @endif

            <div class="note-box">
                <div class="note-icon">&#9432;</div>
                <div>
                    <strong>CATATAN</strong>
                    <p>{{ $certificate->remarks ?: 'Kompetensi ini berlaku sesuai tanggal kedaluwarsa di atas. Perpanjangan wajib dilakukan sebelum masa berlaku berakhir.' }}</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
