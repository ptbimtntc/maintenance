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
    'employee_id', 'certificate_type_id', 'name', 'certificate_number', 'issuing_organization',
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
