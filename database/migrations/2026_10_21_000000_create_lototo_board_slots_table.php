<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lototo_board_slots', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);
            $table->unsignedInteger('position');
            $table->integer('employee_id')->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreignId('spare_lock_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['category', 'position']);
            $table->unique(['category', 'employee_id']);
            $table->unique(['category', 'spare_lock_id']);
            $table->index('category');
        });

        $activeStatusId = DB::table('employment_statuses')
            ->where('code', 'ACTIVE')
            ->value('id');
        $employees = DB::table('employees')
            ->where('business_unit_id', 1)
            ->whereNull('deleted_at')
            ->when(
                $activeStatusId !== null,
                fn ($query) => $query->where('employment_status_id', $activeStatusId)
            )
            ->get(['id', 'lototo_number']);

        $technicians = $employees
            ->filter(fn ($employee) => preg_match('/^\d{1,2}$/', trim((string) $employee->lototo_number)) === 1)
            ->sortBy(fn ($employee) => (int) $employee->lototo_number)
            ->values();
        $supervisors = $employees
            ->filter(fn ($employee) => preg_match('/^\d{3}$/', trim((string) $employee->lototo_number)) === 1)
            ->sortBy(fn ($employee) => (int) $employee->lototo_number)
            ->values();
        $spareLocks = DB::table('spare_locks')->orderBy('code')->get(['id']);
        $now = now();
        $slots = [];

        foreach ([
            'technician' => [$technicians, 65],
            'supervisor' => [$supervisors, 10],
        ] as $category => [$assignedEmployees, $minimumSlots]) {
            $slotCount = max($minimumSlots, $assignedEmployees->count());

            for ($position = 1; $position <= $slotCount; $position++) {
                $slots[] = [
                    'category' => $category,
                    'position' => $position,
                    'employee_id' => $assignedEmployees->get($position - 1)?->id,
                    'spare_lock_id' => null,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $slotCount = max(20, $spareLocks->count());
        for ($position = 1; $position <= $slotCount; $position++) {
            $slots[] = [
                'category' => 'spare',
                'position' => $position,
                'employee_id' => null,
                'spare_lock_id' => $spareLocks->get($position - 1)?->id,
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('lototo_board_slots')->insert($slots);
    }

    public function down(): void
    {
        Schema::dropIfExists('lototo_board_slots');
    }
};
