<?php

namespace App\Console\Commands;

use App\Enums\PermissionName;
use App\Models\Certificate;
use App\Models\Setting;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Notifications\CertificateExpiringSoon;
use App\Notifications\UpcomingTrainingSession;
use Illuminate\Console\Command;

class SendExpirationNotifications extends Command
{
    protected $signature = 'app:send-expiration-notifications';

    protected $description = 'Notify relevant users about certificates entering their "expiring soon" window and upcoming training sessions. Safe to run daily - each certificate/participant is only notified once.';

    public function handle(): int
    {
        $this->notifyExpiringCertificates();
        $this->notifyUpcomingTrainingSessions();

        return self::SUCCESS;
    }

    private function notifyExpiringCertificates(): void
    {
        $certificates = Certificate::query()
            ->whereNull('expiry_notified_at')
            ->where('verification_status', 'verified')
            ->whereNotNull('expiry_date')
            ->with('employee.user')
            ->get()
            ->filter(fn (Certificate $c) => $c->status() === 'expiring_soon');

        if ($certificates->isEmpty()) {
            $this->info('No newly expiring certificates to notify about.');

            return;
        }

        $certificateManagers = User::permission(PermissionName::ManageCertificates->value)->get();

        foreach ($certificates as $certificate) {
            $recipients = $certificateManagers->when(
                $certificate->employee->user,
                fn ($users) => $users->push($certificate->employee->user)
            )->unique('id');

            foreach ($recipients as $recipient) {
                $recipient->notify(new CertificateExpiringSoon($certificate));
            }

            $certificate->forceFill(['expiry_notified_at' => now()])->save();
        }

        $this->info("Notified about {$certificates->count()} expiring certificate(s).");
    }

    private function notifyUpcomingTrainingSessions(): void
    {
        $leadDays = Setting::getInt('training_reminder_days_before', 7);
        $cutoff = now()->addDays($leadDays)->endOfDay();

        $participants = TrainingParticipant::query()
            ->whereNull('reminded_at')
            ->whereHas('trainingSession', fn ($q) => $q
                ->where('status', 'scheduled')
                ->whereBetween('start_date', [now()->startOfDay(), $cutoff]))
            ->with(['employee.user', 'trainingSession.trainingProgram'])
            ->get();

        if ($participants->isEmpty()) {
            $this->info('No upcoming training sessions to remind participants about.');

            return;
        }

        foreach ($participants as $participant) {
            if ($participant->employee->user) {
                $participant->employee->user->notify(new UpcomingTrainingSession($participant->trainingSession));
            }

            $participant->forceFill(['reminded_at' => now()])->save();
        }

        $this->info("Reminded {$participants->count()} participant(s) about upcoming sessions.");
    }
}
