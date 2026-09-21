<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'employee_id', 'certificate_type_id', 'name', 'certificate_number', 'issuing_organization', 'trainer_name', 'authorizer_name', 'authorizer_title',
    'issue_date', 'expiry_date', 'related_skill_id', 'related_training_program_id',
    'verification_status', 'file_path', 'file_original_name', 'verification_notes', 'remarks',
    'previous_certificate_id', 'created_by', 'updated_by',
])]
class Certificate extends Model
{
    /** @use HasFactory<\Database\Factories\CertificateFactory> */
    use HasFactory, SoftDeletes, Auditable;

    public const VERIFICATION_STATUSES = ['pending_verification', 'verified'];

    /**
     * How many days before expiry a certificate is flagged "expiring soon".
     * Configurable via Administration -> Settings rather than a fixed
     * number, since different certificate types may warrant different
     * lead times in practice.
     */
    public static function expiringSoonDays(): int
    {
        return Setting::getInt('certificate_expiring_soon_days', 60);
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class);
    }

    public function relatedSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'related_skill_id');
    }

    public function relatedTrainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'related_training_program_id');
    }

    public function previousCertificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'previous_certificate_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Certificate::class, 'previous_certificate_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Expiry-based status is derived, never stored, so it can't go stale.
     * A certificate is never assumed valid just because a file was uploaded.
     */
    public function status(): string
    {
        if ($this->verification_status === 'pending_verification') {
            return 'pending_verification';
        }

        if (! $this->expiry_date) {
            return 'no_expiry';
        }

        $today = now()->startOfDay();
        $expiry = $this->expiry_date->copy()->startOfDay();

        if ($expiry->lt($today)) {
            return 'expired';
        }

        if ($today->diffInDays($expiry) <= self::expiringSoonDays()) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    /**
     * Automatic certificate number, PTBI/{year}/{month in roman}/{code}/{seq},
     * e.g. PTBI/2026/IX/BMM/00001. The code is the program's code (or its
     * first three letters); the sequence counts the numbers already issued
     * for that program.
     */
    public static function generateNumber(TrainingProgram $program, \Illuminate\Support\Carbon $date): string
    {
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'][$date->month];
        $code = trim((string) $program->code) !== ''
            ? strtoupper($program->code)
            : str_pad(substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $program->title)), 0, 3), 3, 'X');
        $sequence = self::withTrashed()->where('related_training_program_id', $program->id)->whereNotNull('certificate_number')->count() + 1;

        return sprintf('PTBI/%d/%s/%s/%05d', $date->year, $roman, $code, $sequence);
    }

    public static function statusLabels(): array
    {
        return [
            'valid' => 'Valid',
            'expiring_soon' => 'Expiring Soon',
            'expired' => 'Expired',
            'no_expiry' => 'No Expiry',
            'pending_verification' => 'Pending Verification',
        ];
    }
}
