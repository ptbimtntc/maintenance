<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Employee;
use App\Models\TrainingParticipant;
use App\Models\TrainingRecord;
use Illuminate\View\View;

/**
 * The unauthenticated "scan the QR code on my badge" pages an employee
 * points people at to prove which certifications they hold - no login,
 * reachable by anyone with the link. Only verified, numbered certificates
 * are ever shown here, matching what CertificateController@show considers
 * presentable.
 */
class PublicCertificateController extends Controller
{
    public function employee(Employee $employee): View
    {
        $employee->load(['department', 'position']);

        $certificates = $employee->certificates()
            ->where('verification_status', 'verified')
            ->whereNotNull('certificate_number')
            ->with('certificateType')
            ->orderByDesc('issue_date')
            ->get();

        // Trainings scheduled but not yet passed - either the quiz hasn't
        // been taken yet ("Assigned") or it was taken and failed
        // ("Failed"); a pass already shows up above as a Certificate, so
        // certificate_id being set is what excludes it here.
        $pendingParticipations = $employee->trainingParticipations()
            ->whereNull('certificate_id')
            ->with('trainingSession.trainingProgram')
            ->get()
            ->sortBy(fn (TrainingParticipant $p) => $p->trainingSession->start_date)
            ->values();

        return view('certificates.public-profile', [
            'employee' => $employee,
            'certificates' => $certificates,
            'pendingParticipations' => $pendingParticipations,
        ]);
    }

    /**
     * Read-only info for a scheduled-but-not-yet-passed training - no quiz
     * can be taken here (that stays behind login at
     * TrainingQuizController::show, gated to the employee's own account).
     */
    public function participant(TrainingParticipant $participant): View
    {
        $participant->load(['employee.department', 'employee.position', 'trainingSession.trainingProgram']);

        abort_if($participant->certificate_id !== null, 404);

        return view('certificates.public-participant', ['participant' => $participant]);
    }

    /**
     * The "DETAIL" tab an employee lands on after tapping a certification
     * in their public profile - general info plus a "SERTIFIKAT" tab that
     * only unlocks once the certificate is verified and numbered.
     */
    public function detail(Certificate $certificate): View
    {
        $certificate->load(['employee.department', 'employee.position', 'certificateType', 'relatedSkill', 'relatedTrainingProgram']);

        abort_unless($certificate->verification_status === 'verified', 404);

        $score = $this->resolveScore($certificate);

        return view('certificates.public-detail', ['certificate' => $certificate, 'score' => $score]);
    }

    public function certificate(Certificate $certificate): View
    {
        abort_unless($certificate->verification_status === 'verified' && filled($certificate->certificate_number), 404);

        $certificate->load(['employee', 'certificateType', 'relatedTrainingProgram']);

        return view('certificates.show', [
            'certificate' => $certificate,
            'available' => true,
            'score' => $this->resolveScore($certificate),
            'public' => true,
        ]);
    }

    private function resolveScore(Certificate $certificate): ?float
    {
        if (! $certificate->related_training_program_id) {
            return null;
        }

        return TrainingRecord::where('employee_id', $certificate->employee_id)
            ->where('training_program_id', $certificate->related_training_program_id)
            ->whereNotNull('assessment_score')
            ->orderByDesc('training_date')
            ->value('assessment_score');
    }
}
