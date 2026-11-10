<?php

namespace Database\Seeders;

use App\Models\Machine;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('data/machines.csv');
        if (! file_exists($csvPath)) {
            return;
        }

        $handle = fopen($csvPath, 'r');
        if (! $handle) {
            return;
        }

        fgetcsv($handle); // skip header line

        $now = now();
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue;
            }

            $machineNo = trim((string) $row[0]);
            $funcLocation = trim((string) $row[1]);
            $machineType = trim((string) $row[2]);

            if ($machineNo === '') {
                continue;
            }

            $batch[] = [
                'machine_no' => $machineNo,
                'func_location' => $funcLocation ?: null,
                'machine_type' => $machineType,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 200) {
                Machine::upsert($batch, ['machine_no'], ['func_location', 'machine_type', 'updated_at']);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            Machine::upsert($batch, ['machine_no'], ['func_location', 'machine_type', 'updated_at']);
        }

        fclose($handle);
    }
}

