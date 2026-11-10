<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lototo_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('lototo_activities', 'supervisor_follow_up')) {
                $table->text('supervisor_follow_up')->nullable();
            }

            if (! Schema::hasColumn('lototo_activities', 'supervisor_follow_up_by')) {
                $this->addReferencedIdColumn($table, 'supervisor_follow_up_by', 'users', nullable: true);
            }

            if (! Schema::hasColumn('lototo_activities', 'supervisor_follow_up_at')) {
                $table->timestamp('supervisor_follow_up_at')->nullable();
            }

            if (! Schema::hasColumn('lototo_activities', 'supervisor_follow_up_status')) {
                $table->string('supervisor_follow_up_status', 20)->default('not_required');
            }
        });

        $hasSupervisorForeignKey = collect(Schema::getForeignKeys('lototo_activities'))
            ->contains(fn (array $foreignKey) => in_array('supervisor_follow_up_by', $foreignKey['columns'], true));

        // Repair a column left by an earlier run with another type before adding the foreign key.
        if (! $hasSupervisorForeignKey && in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $column = collect(Schema::getColumns('lototo_activities'))
                ->firstWhere('name', 'supervisor_follow_up_by');
            if (! $column) {
                throw new RuntimeException('Could not inspect lototo_activities.supervisor_follow_up_by before adding its foreign key.');
            }

            if ($this->normalizedIntegerType((string) $column['type']) !== $this->referencedIdType('users')) {
                Schema::table('lototo_activities', function (Blueprint $table) {
                    $this->addReferencedIdColumn($table, 'supervisor_follow_up_by', 'users', nullable: true)->change();
                });
            }
        }

        if (! $hasSupervisorForeignKey) {
            Schema::table('lototo_activities', function (Blueprint $table) {
                $table->foreign('supervisor_follow_up_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $hasSupervisorForeignKey = collect(Schema::getForeignKeys('lototo_activities'))
            ->contains(fn (array $foreignKey) => in_array('supervisor_follow_up_by', $foreignKey['columns'], true));

        Schema::table('lototo_activities', function (Blueprint $table) use ($hasSupervisorForeignKey) {
            if ($hasSupervisorForeignKey) {
                $table->dropForeign(['supervisor_follow_up_by']);
            }

            $table->dropColumn([
                'supervisor_follow_up',
                'supervisor_follow_up_at',
                'supervisor_follow_up_status',
                'supervisor_follow_up_by',
            ]);
        });
    }

    private function addReferencedIdColumn(
        Blueprint $table,
        string $column,
        string $referencedTable,
        bool $nullable = false
    ): \Illuminate\Database\Schema\ColumnDefinition
    {
        $type = $this->referencedIdType($referencedTable);
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