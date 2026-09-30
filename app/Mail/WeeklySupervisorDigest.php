<?php

namespace App\Mail;

use App\Models\Certificate;
use App\Models\Employee;
use App\Models\TrainingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class WeeklySupervisorDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Certificate>  $expiringCertificates
     * @param  Collection<int, Certificate>  $expiredCertificates
     * @param  Collection<int, TrainingSession>  $upcomingSessions
     * @param  Collection<int, Employee>  $employeesWithSkillGaps
     */
    public function __construct(
        public readonly Employee $supervisor,
        public readonly Collection $expiringCertificates,
        public readonly Collection $expiredCertificates,
        public readonly Collection $upcomingSessions,
        public readonly Collection $employeesWithSkillGaps,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Weekly Team Summary - '.now()->format('d M Y'))
            ->view('emails.weekly-supervisor-digest');
    }
}
