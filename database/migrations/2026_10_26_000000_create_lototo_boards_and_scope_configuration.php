<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyBusinessUnitIds = DB::table('lototo_board_slots')
            ->join('employees', 'employees.id', '=', 'lototo_board_slots.employee_id')
            ->whereNotNull('lototo_board_slots.employee_id')
            ->distinct()
            ->pluck('employees.business_unit_id');

        if ($legacyBusinessUnitIds->count() > 1
            || ($legacyBusinessUnitIds->isEmpty() && DB::table('business_units')->exists())) {
            throw new RuntimeException('Cannot safely identify the legacy LOTOTO board Business Unit from its saved slot assignments.');
        }

        $legacyBusinessUnitId = $legacyBusinessUnitIds->isEmpty() ? null : (int) $legacyBusinessUnitIds->first();
        $legacyBusinessUnit = $legacyBusinessUnitId
            ? DB::table('business_units')->where('id', $legacyBusinessUnitId)->first()
            : null;
        if ($legacyBusinessUnitId && ! $legacyBusinessUnit) {
            throw new RuntimeException('The legacy LOTOTO board Business Unit no longer exists.');
        }

        if (! Schema::hasTable('lototo_boards')) {
            Schema::create('lototo_boards', function (Blueprint $table) {
                $table->id();
                $table->integer('business_unit_id');
                $table->string('division_name', 100);
                $table->string('code', 80);
                $table->string('title', 160);
                $table->string('subtitle', 255)->default('Daftar LOTOTO karyawan dan spare');
                $table->json('layout')->nullable();
                $table->json('component_settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } elseif (Schema::getColumnType('lototo_boards', 'business_unit_id') !== 'integer') {
            // Recover safely if MySQL created the table before rejecting the original BIGINT foreign key.
            DB::statement('ALTER TABLE lototo_boards MODIFY business_unit_id INT NOT NULL');
        }

        $indexNames = collect(Schema::getIndexes('lototo_boards'))->pluck('name');
        Schema::table('lototo_boards', function (Blueprint $table) use ($indexNames) {
            if (! $indexNames->contains('lototo_boards_code_unique')) {
                $table->unique('code', 'lototo_boards_code_unique');
            }
            if (! $indexNames->contains('lototo_boards_bu_division_unique')) {
                $table->unique(['business_unit_id', 'division_name'], 'lototo_boards_bu_division_unique');
            }
            if (! $indexNames->contains('lototo_boards_bu_active_index')) {
                $table->index(['business_unit_id', 'is_active'], 'lototo_boards_bu_active_index');
            }
        });

        $foreignKeys = collect(Schema::getForeignKeys('lototo_boards'));
        if (! $foreignKeys->contains(fn (array $foreignKey) => $foreignKey['columns'] === ['business_unit_id'])) {
            Schema::table('lototo_boards', function (Blueprint $table) {
                $table->foreign('business_unit_id', 'lototo_boards_business_unit_id_foreign')
                    ->references('id')->on('business_units')->restrictOnDelete();
            });
        }

        $boardId = DB::table('lototo_boards')->where('code', 'mtn_tr')->value('id');
        if (! $boardId && $legacyBusinessUnit) {
            $boardId = DB::table('lototo_boards')->insertGetId([
                'business_unit_id' => $legacyBusinessUnitId,
                'division_name' => 'Maintenance',
                'code' => 'mtn_tr',
                'title' => 'MAINTENANCE · '.$legacyBusinessUnit->name,
                'subtitle' => 'Daftar LOTOTO karyawan dan spare',
                'layout' => json_encode(['technician_columns' => 20, 'supervisor_columns' => 10, 'section_order' => ['spare', 'other']]),
                'component_settings' => json_encode(['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('lototo_board_slots', function (Blueprint $table) {
            $table->foreignId('board_id')->nullable()->constrained('lototo_boards')->restrictOnDelete();
        });
        if ($boardId) {
            DB::table('lototo_board_slots')->whereNull('board_id')->update(['board_id' => $boardId]);
        }

        Schema::table('spare_locks', function (Blueprint $table) {
            $table->foreignId('board_id')->nullable()->constrained('lototo_boards')->restrictOnDelete();
        });
        if ($boardId) {
            DB::table('spare_locks')->whereNull('board_id')->update(['board_id' => $boardId]);
        }

        Schema::table('lototo_board_slots', function (Blueprint $table) {
            $table->dropUnique('lototo_board_slots_category_position_unique');
            $table->dropUnique('lototo_board_slots_category_employee_id_unique');
            $table->dropUnique('lototo_board_slots_category_spare_lock_id_unique');
            $table->unique(['board_id', 'category', 'position'], 'lototo_slots_board_category_position_unique');
            $table->unique(['board_id', 'category', 'employee_id'], 'lototo_slots_board_category_employee_unique');
            $table->unique(['board_id', 'category', 'spare_lock_id'], 'lototo_slots_board_category_spare_unique');
            $table->index(['board_id', 'category'], 'lototo_slots_board_category_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('lototo_boards') && DB::table('lototo_boards')->exists()) {
            throw new RuntimeException('LOTOTO board rollback blocked while board configuration exists. Export the board configuration and plan an explicit data migration before rollback.');
        }

        Schema::table('lototo_board_slots', function (Blueprint $table) {
            $table->dropUnique('lototo_slots_board_category_position_unique');
            $table->dropUnique('lototo_slots_board_category_employee_unique');
            $table->dropUnique('lototo_slots_board_category_spare_unique');
            $table->dropIndex('lototo_slots_board_category_index');
            $table->dropConstrainedForeignId('board_id');
            $table->unique(['category', 'position']);
            $table->unique(['category', 'employee_id']);
            $table->unique(['category', 'spare_lock_id']);
        });

        Schema::table('spare_locks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_id');
        });

        Schema::dropIfExists('lototo_boards');
    }
};
