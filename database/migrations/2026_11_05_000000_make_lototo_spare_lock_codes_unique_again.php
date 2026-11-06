<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spare lock codes are made by the application from the division (DRAMIX-SP-001, MAINTENANCE-HP-SP-001) and must
     * be unique across all boards, also when two admins create boards or locks at the same time. The per-board unique
     * index stays. No row is changed: if two boards already share a code the migration stops and names it.
     */
    public function up(): void
    {
        $shared = DB::table('spare_locks')->select('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->pluck('code');
        if ($shared->isNotEmpty()) {
            throw new RuntimeException('Spare lock codes used on more than one board: '.$shared->implode(', ').'. Rename them before making codes unique across boards.');
        }

        if (! collect(Schema::getIndexes('spare_locks'))->pluck('name')->contains('spare_locks_code_unique')) {
            Schema::table('spare_locks', function (Blueprint $table) {
                $table->unique('code', 'spare_locks_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (collect(Schema::getIndexes('spare_locks'))->pluck('name')->contains('spare_locks_code_unique')) {
            Schema::table('spare_locks', function (Blueprint $table) {
                $table->dropUnique('spare_locks_code_unique');
            });
        }
    }
};
