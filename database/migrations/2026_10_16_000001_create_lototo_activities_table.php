<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REQUIRED_ACTIVITY_COLUMNS = [
        'id',
        'employee_id',
        'spare_lock_id',
        'board',
        'location',
        'activity',
        'risks',
        'other_risk',
        'risk_controls',
        'knows_procedure',
        'follows_lototo',
        'uses_ppe',
        'safe_tools',
        'safe_hands',
        'supervisor_feedback',
        'status',
        'outcome',
        'out_details',
        'started_at',
        'ended_at',
        'created_at',
        'updated_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('lototo_activities')) {
            Schema::create('lototo_activities', function (Blueprint $table) {
                $table->id();
                $this->addReferencedIdColumn($table, 'employee_id', 'employees');
                $this->addReferencedIdColumn($table, 'spare_lock_id', 'spare_locks', nullable: true);
                $table->string('board', 30);
                $table->string('location');
                $table->text('activity');
                $table->json('risks');
                $table->string('other_risk')->nullable();
                $table->text('risk_controls');
                $table->boolean('knows_procedure');
                $table->boolean('follows_lototo');
                $table->boolean('uses_ppe');
                $table->boolean('safe_tools');
                $table->boolean('safe_hands');
                $table->text('supervisor_feedback')->nullable();
                $table->string('status', 20)->default('in_progress');
                $table->string('outcome', 20)->nullable();
                $table->json('out_details')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'status']);
                $table->index(['spare_lock_id', 'started_at']);
            });
        } else {
            $this->validateExistingActivityTable();
        }

        $this->ensureForeignKey('lototo_activities', 'employee_id', 'employees', 'id', 'restrict', nullable: false);
        $this->ensureForeignKey('lototo_activities', 'spare_lock_id', 'spare_locks', 'id', 'set null', nullable: true);
        $this->ensureActivityIndex(['employee_id', 'status'], 'lototo_activities_employee_id_status_index');
        $this->ensureActivityIndex(['spare_lock_id', 'started_at'], 'lototo_activities_spare_lock_id_started_at_index');

        if (! Schema::hasColumn('spare_locks', 'current_activity_id')) {
            Schema::table('spare_locks', function (Blueprint $table) {
                $this->addReferencedIdColumn($table, 'current_activity_id', 'lototo_activities', nullable: true)
                    ->after('is_active');
            });
        }

        $this->ensureForeignKey('spare_locks', 'current_activity_id', 'lototo_activities', 'id', 'set null', nullable: true);
    }

    public function down(): void
    {
        if (Schema::hasTable('lototo_activities') && DB::table('lototo_activities')->exists()) {
            throw new RuntimeException('LOTOTO activity data exists. Export it before rolling back; rollback refused.');
        }

        if (Schema::hasColumn('spare_locks', 'current_activity_id')) {
            if (DB::table('spare_locks')->whereNotNull('current_activity_id')->exists()) {
                throw new RuntimeException('Spare locks reference a LOTOTO activity. Clear or migrate those references before rollback; rollback refused.');
            }

            $foreignKey = collect(Schema::getForeignKeys('spare_locks'))
                ->firstWhere('columns', ['current_activity_id']);

            Schema::table('spare_locks', function (Blueprint $table) use ($foreignKey) {
                if ($foreignKey) {
                    $table->dropForeign($foreignKey['name']);
                }

                $table->dropColumn('current_activity_id');
            });
        }

        Schema::dropIfExists('lototo_activities');
    }

    private function validateExistingActivityTable(): void
    {
        $columns = collect(Schema::getColumns('lototo_activities'))->pluck('name');
        $missing = array_values(array_diff(self::REQUIRED_ACTIVITY_COLUMNS, $columns->all()));
        if ($missing !== []) {
            throw new RuntimeException(
                'Existing lototo_activities is missing required columns: '.implode(', ', $missing)
                .'. Back up and reconcile this table before running the LOTOTO migration.'
            );
        }

        $hasPrimaryKey = collect(Schema::getIndexes('lototo_activities'))
            ->contains(fn (array $index) => $index['primary'] ?? false);
        if (! $hasPrimaryKey) {
            throw new RuntimeException('Existing lototo_activities has no primary key. Back up and reconcile it before running the LOTOTO migration.');
        }
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete,
        bool $nullable
    ): void {
        $childColumn = collect(Schema::getColumns($table))->firstWhere('name', $column);
        $parentColumn = collect(Schema::getColumns($referencedTable))->firstWhere('name', $referencedColumn);
        if (! $childColumn || ! $parentColumn) {
            throw new RuntimeException("Cannot verify {$table}.{$column} -> {$referencedTable}.{$referencedColumn}.");
        }

        $foreignKeys = collect(Schema::getForeignKeys($table))
            ->filter(fn (array $foreignKey) => $foreignKey['columns'] === [$column]);
        if ($foreignKeys->isNotEmpty()) {
            if ((bool) ($childColumn['nullable'] ?? false) !== $nullable) {
                throw new RuntimeException("{$table}.{$column} has a foreign key but its nullability does not match the LOTOTO schema.");
            }

            $matches = $foreignKeys->contains(function (array $foreignKey) use ($referencedTable, $referencedColumn, $onDelete) {
                $actualDeleteRule = strtolower(str_replace([' ', '_'], '', (string) ($foreignKey['on_delete'] ?? '')));
                $expectedDeleteRule = str_replace(' ', '', $onDelete);

                return $foreignKey['foreign_table'] === $referencedTable
                    && $foreignKey['foreign_columns'] === [$referencedColumn]
                    && in_array($actualDeleteRule, [$expectedDeleteRule, $onDelete === 'restrict' ? 'noaction' : $expectedDeleteRule], true);
            });

            if (! $matches) {
                throw new RuntimeException("{$table}.{$column} already has a foreign key with a different target or delete rule.");
            }

            return;
        }

        $this->alignForeignKeyColumnType($table, $column, $childColumn, (string) $parentColumn['type'], $nullable);
        $orphans = DB::table("{$table} as child")
            ->leftJoin("{$referencedTable} as parent", "parent.{$referencedColumn}", '=', "child.{$column}")
            ->whereNotNull("child.{$column}")
            ->whereNull("parent.{$referencedColumn}")
            ->exists();
        if ($orphans) {
            throw new RuntimeException("Cannot add {$table}.{$column} foreign key: existing values do not all reference {$referencedTable}.{$referencedColumn}.");
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $onDelete) {
            $foreign = $blueprint->foreign($column)->references($referencedColumn)->on($referencedTable);
            if ($onDelete === 'set null') {
                $foreign->nullOnDelete();
            } else {
                $foreign->restrictOnDelete();
            }
        });
    }

    private function alignForeignKeyColumnType(
        string $table,
        string $column,
        array $childColumn,
        string $parentType,
        bool $nullable
    ): void
    {
        $actualType = $this->normalizedIntegerType((string) $childColumn['type']);
        $targetType = $this->normalizedIntegerType($parentType);
        $actualNullability = (bool) ($childColumn['nullable'] ?? false);
        if ($actualType === $targetType && $actualNullability === $nullable) {
            return;
        }

        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Cannot safely align {$table}.{$column} from {$actualType} to {$targetType} on this database driver.");
        }

        if (($childColumn['default'] ?? null) !== null) {
            throw new RuntimeException("Cannot safely align {$table}.{$column}: its existing default requires manual review.");
        }

        if (! $nullable && $actualNullability && DB::table($table)->whereNull($column)->exists()) {
            throw new RuntimeException("Cannot safely make {$table}.{$column} NOT NULL: existing rows contain null values.");
        }

        [$minimum, $maximum] = $this->integerBounds($targetType);
        $outOfRange = DB::table($table)
            ->whereNotNull($column)
            ->whereRaw(
                "CAST(`{$column}` AS DECIMAL(20, 0)) < ? OR CAST(`{$column}` AS DECIMAL(20, 0)) > ?",
                [$minimum, $maximum]
            )
            ->exists();
        if ($outOfRange) {
            throw new RuntimeException("Cannot safely align {$table}.{$column}: existing values do not fit {$targetType}.");
        }

        $nullability = $nullable ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$targetType} {$nullability}");
    }

    private function ensureActivityIndex(array $columns, string $name): void
    {
        $exists = collect(Schema::getIndexes('lototo_activities'))
            ->contains(fn (array $index) => $index['columns'] === $columns);
        if (! $exists) {
            Schema::table('lototo_activities', fn (Blueprint $table) => $table->index($columns, $name));
        }
    }

    private function addReferencedIdColumn(
        Blueprint $table,
        string $column,
        string $referencedTable,
        bool $nullable = false
    ): \Illuminate\Database\Schema\ColumnDefinition {
        $id = collect(Schema::getColumns($referencedTable))->firstWhere('name', 'id');
        if (! $id) {
            throw new RuntimeException("Could not inspect {$referencedTable}.id before creating its foreign key.");
        }

        $type = $this->normalizedIntegerType((string) $id['type']);
        $definition = str_starts_with($type, 'bigint')
            ? $table->bigInteger($column)
            : $table->integer($column);

        if (str_contains($type, 'unsigned')) {
            $definition->unsigned();
        }
        if ($nullable) {
            $definition->nullable();
        }

        return $definition;
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

    private function integerBounds(string $type): array
    {
        return match ($type) {
            'int unsigned' => ['0', '4294967295'],
            'int' => ['-2147483648', '2147483647'],
            'bigint unsigned' => ['0', '18446744073709551615'],
            'bigint' => ['-9223372036854775808', '9223372036854775807'],
        };
    }
};
