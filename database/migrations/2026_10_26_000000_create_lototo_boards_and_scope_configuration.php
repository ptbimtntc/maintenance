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

        if ($legacyBusinessUnitIds->count() > 1) {
            throw new RuntimeException('Cannot safely identify the legacy LOTOTO board Business Unit from its saved slot assignments.');
        }

        $legacyBusinessUnitId = $legacyBusinessUnitIds->first() ?: DB::table('business_units')->value('id');
        $legacyBusinessUnit = $legacyBusinessUnitId
            ? DB::table('business_units')->where('id', $legacyBusinessUnitId)->first()
            : null;
        if ($legacyBusinessUnitId && ! $legacyBusinessUnit) {
            throw new RuntimeException('The legacy LOTOTO board Business Unit no longer exists.');
        }

        if (! Schema::hasTable('lototo_boards')) {
            Schema::create('lototo_boards', function (Blueprint $table) {
                $table->id();
                $this->addReferencedIdColumn($table, 'business_unit_id', 'business_units');
                $table->string('division_name', 100);
                $table->string('code', 80);
                $table->string('title', 160);
                $table->string('subtitle', 255)->default('Daftar LOTOTO karyawan dan spare');
                $table->json('layout')->nullable();
                $table->json('component_settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } elseif (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // Recover a table left by an earlier failed run with a type that does not match business_units.id.
            $column = collect(Schema::getColumns('lototo_boards'))->firstWhere('name', 'business_unit_id');
            if (! $column) {
                throw new RuntimeException('Could not inspect lototo_boards.business_unit_id before adding its foreign key.');
            }

            $businessUnitForeignKey = collect(Schema::getForeignKeys('lototo_boards'))
                ->firstWhere('columns', ['business_unit_id']);
            if ($businessUnitForeignKey && $businessUnitForeignKey['foreign_table'] !== 'business_units') {
                throw new RuntimeException('lototo_boards.business_unit_id already references a different table.');
            }
            if (! $businessUnitForeignKey) {
                $type = $this->normalizedIntegerType((string) $column['type']);
                $referencedType = $this->referencedIdType('business_units');
                if ($type !== $referencedType) {
                    DB::statement('ALTER TABLE lototo_boards MODIFY business_unit_id '.$referencedType.' NOT NULL');
                }
            }
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
        $businessUnitForeignKey = $foreignKeys->firstWhere('columns', ['business_unit_id']);
        if (! $businessUnitForeignKey) {
            Schema::table('lototo_boards', function (Blueprint $table) {
                $table->foreign('business_unit_id', 'lototo_boards_business_unit_id_foreign')
                    ->references('id')->on('business_units')->restrictOnDelete();
            });
        } elseif ($businessUnitForeignKey['foreign_table'] !== 'business_units') {
            throw new RuntimeException('lototo_boards.business_unit_id already references a different table.');
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

        $this->ensureBoardReference('lototo_board_slots');
        if ($boardId) {
            DB::table('lototo_board_slots')->whereNull('board_id')->update(['board_id' => $boardId]);
        }

        $this->ensureBoardReference('spare_locks');
        if ($boardId) {
            DB::table('spare_locks')->whereNull('board_id')->update(['board_id' => $boardId]);
        }

        $indexes = collect(Schema::getIndexes('lototo_board_slots'));
        Schema::table('lototo_board_slots', function (Blueprint $table) use ($indexes) {
            foreach ([
                'lototo_board_slots_category_position_unique',
                'lototo_board_slots_category_employee_id_unique',
                'lototo_board_slots_category_spare_lock_id_unique',
            ] as $index) {
                if ($indexes->contains('name', $index)) {
                    $table->dropUnique($index);
                }
            }
        });

        $indexes = collect(Schema::getIndexes('lototo_board_slots'))->pluck('name');
        Schema::table('lototo_board_slots', function (Blueprint $table) use ($indexes) {
            if (! $indexes->contains('lototo_slots_board_category_position_unique')) {
                $table->unique(['board_id', 'category', 'position'], 'lototo_slots_board_category_position_unique');
            }
            if (! $indexes->contains('lototo_slots_board_category_employee_unique')) {
                $table->unique(['board_id', 'category', 'employee_id'], 'lototo_slots_board_category_employee_unique');
            }
            if (! $indexes->contains('lototo_slots_board_category_spare_unique')) {
                $table->unique(['board_id', 'category', 'spare_lock_id'], 'lototo_slots_board_category_spare_unique');
            }
            if (! $indexes->contains('lototo_slots_board_category_index')) {
                $table->index(['board_id', 'category'], 'lototo_slots_board_category_index');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('lototo_boards') && DB::table('lototo_boards')->exists()) {
            throw new RuntimeException('LOTOTO board rollback blocked while board configuration exists. Export the board configuration and plan an explicit data migration before rollback.');
        }

        if (Schema::hasColumn('lototo_board_slots', 'board_id')) {
            $foreignKey = collect(Schema::getForeignKeys('lototo_board_slots'))
                ->firstWhere('columns', ['board_id']);
            if ($foreignKey) {
                Schema::table('lototo_board_slots', function (Blueprint $table) use ($foreignKey) {
                    $table->dropForeign($foreignKey['name']);
                });
            }
        }

        $indexes = collect(Schema::getIndexes('lototo_board_slots'))->pluck('name');
        Schema::table('lototo_board_slots', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('lototo_slots_board_category_position_unique')) {
                $table->dropUnique('lototo_slots_board_category_position_unique');
            }
            if ($indexes->contains('lototo_slots_board_category_employee_unique')) {
                $table->dropUnique('lototo_slots_board_category_employee_unique');
            }
            if ($indexes->contains('lototo_slots_board_category_spare_unique')) {
                $table->dropUnique('lototo_slots_board_category_spare_unique');
            }
            if ($indexes->contains('lototo_slots_board_category_index')) {
                $table->dropIndex('lototo_slots_board_category_index');
            }
        });

        if (Schema::hasColumn('lototo_board_slots', 'board_id')) {
            Schema::table('lototo_board_slots', function (Blueprint $table) {
                $table->dropColumn('board_id');
            });
        }

        $indexes = collect(Schema::getIndexes('lototo_board_slots'))->pluck('name');
        Schema::table('lototo_board_slots', function (Blueprint $table) use ($indexes) {
            if (! $indexes->contains('lototo_board_slots_category_position_unique')) {
                $table->unique(['category', 'position']);
            }
            if (! $indexes->contains('lototo_board_slots_category_employee_id_unique')) {
                $table->unique(['category', 'employee_id']);
            }
            if (! $indexes->contains('lototo_board_slots_category_spare_lock_id_unique')) {
                $table->unique(['category', 'spare_lock_id']);
            }
        });

        Schema::table('spare_locks', function (Blueprint $table) {
            if (Schema::hasColumn('spare_locks', 'board_id')) {
                $foreignKey = collect(Schema::getForeignKeys('spare_locks'))
                    ->firstWhere('columns', ['board_id']);
                if ($foreignKey) {
                    $table->dropForeign($foreignKey['name']);
                }
                $table->dropColumn('board_id');
            }
        });

        Schema::dropIfExists('lototo_boards');
    }

    private function ensureBoardReference(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'board_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('board_id')->nullable()->constrained('lototo_boards')->restrictOnDelete();
            });

            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys($tableName))
            ->filter(fn (array $foreignKey) => $foreignKey['columns'] === ['board_id']);
        if ($foreignKeys->contains(fn (array $foreignKey) => $foreignKey['foreign_table'] === 'lototo_boards')) {
            return;
        }
        if ($foreignKeys->isNotEmpty()) {
            throw new RuntimeException("The {$tableName}.board_id column already references a different table.");
        }

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $column = collect(Schema::getColumns($tableName))->firstWhere('name', 'board_id');
            if (! $column) {
                throw new RuntimeException("The {$tableName}.board_id column could not be inspected.");
            }

            $type = strtolower((string) $column['type']);
            if (! (str_starts_with($type, 'bigint') && str_contains($type, 'unsigned'))) {
                DB::statement("ALTER TABLE `{$tableName}` MODIFY `board_id` BIGINT UNSIGNED NULL");
            }
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreign('board_id')->references('id')->on('lototo_boards')->restrictOnDelete();
        });
    }

    private function addReferencedIdColumn(Blueprint $table, string $column, string $referencedTable): void
    {
        $type = $this->referencedIdType($referencedTable);
        $definition = str_starts_with($type, 'bigint')
            ? $table->bigInteger($column)
            : $table->integer($column);

        if (str_contains($type, 'unsigned')) {
            $definition->unsigned();
        }
    }

    private function referencedIdType(string $table): string
    {
        $id = collect(Schema::getColumns($table))->firstWhere('name', 'id');
        if (! $id) {
            throw new RuntimeException("Could not inspect {$table}.id before creating its foreign key.");
        }

        return $this->normalizedIntegerType((string) $id['type']);
    }

    private function normalizedIntegerType(string $type): string
    {
        $type = strtolower($type);
        foreach (['bigint', 'int'] as $integerType) {
            if (str_starts_with($type, $integerType)) {
                return $integerType.(str_contains($type, 'unsigned') ? ' unsigned' : '');
            }
        }

        throw new RuntimeException("Unsupported integer ID type: {$type}.");
    }
};
