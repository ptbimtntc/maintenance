<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class CertificateSeeder extends Seeder
{
    /**
     * Sample certificates covering every computed status (valid, expiring
     * soon, expired, no expiry, pending verification) using the demo
     * employees already linked to login accounts.
     */
    public function run(): void
    {
        $types = [
            'CT-K3' => 'K3 Workplace Safety Certification',
            'CT-WELD' => 'Welding Certification',
            'CT-ELEC' => 'Electrical Competency Certificate',
        ];
        $typeModels = [];
        foreach ($types as $code => $name) {
            $typeModels[$code] = CertificateType::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $admin = User::where('email', 'admin@mpd.test')->first();

        $employeeFor = fn (string $email) => Employee::whereHas('user', fn ($q) => $q->where('email', $email))->first();

        $staff = $employeeFor('staff@mpd.test');
        $supervisor = $employeeFor('supervisor@mpd.test');
        $manager = $employeeFor('manager@mpd.test');

        if ($staff) {
            Certificate::firstOrCreate(
                ['employee_id' => $staff->id, 'name' => 'K3 Workplace Safety Certification'],
                [
                    'certificate_type_id' => $typeModels['CT-K3']->id,
                    'certificate_number' => 'K3-2025-0041',
                    'issuing_organization' => 'National Safety Board (sample)',
                    'issue_date' => now()->subYears(2)->subDays(20),
                    'expiry_date' => now()->addDays(30),
                    'verification_status' => 'verified',
                    'created_by' => $admin?->id,
                ]
            );
        }

        if ($supervisor) {
            Certificate::firstOrCreate(
                ['employee_id' => $supervisor->id, 'name' => 'Welding Certification'],
                [
                    'certificate_type_id' => $typeModels['CT-WELD']->id,
                    'certificate_number' => 'WLD-2021-1187',
                    'issuing_organization' => 'National Certification Institute (sample)',
                    'issue_date' => now()->subYears(3),
                    'expiry_date' => now()->subMonths(2),
                    'verification_status' => 'verified',
                    'created_by' => $admin?->id,
                ]
            );
        }

        if ($manager) {
            Certificate::firstOrCreate(
                ['employee_id' => $manager->id, 'name' => 'Electrical Competency Certificate'],
                [
                    'certificate_type_id' => $typeModels['CT-ELEC']->id,
                    'certificate_number' => 'ELC-2024-0532',
                    'issuing_organization' => 'National Certification Institute (sample)',
                    'issue_date' => now()->subYear(),
                    'expiry_date' => now()->addYears(2),
                    'verification_status' => 'verified',
                    'created_by' => $admin?->id,
                ]
            );

            Certificate::firstOrCreate(
                ['employee_id' => $manager->id, 'name' => 'Internal Trainer Recognition'],
                [
                    'issuing_organization' => 'PT Bekaert Indonesia (sample, internal)',
                    'issue_date' => now()->subMonths(6),
                    'expiry_date' => null,
                    'verification_status' => 'verified',
                    'remarks' => 'No expiry - internal recognition only.',
                    'created_by' => $admin?->id,
                ]
            );

            Certificate::firstOrCreate(
                ['employee_id' => $manager->id, 'name' => 'Advanced PLC Programming (awaiting verification)'],
                [
                    'issuing_organization' => 'Sample External Institute',
                    'issue_date' => now()->subWeek(),
                    'expiry_date' => now()->addYears(3),
                    'verification_status' => 'pending_verification',
                    'created_by' => $admin?->id,
                ]
            );
        }
    }
}
