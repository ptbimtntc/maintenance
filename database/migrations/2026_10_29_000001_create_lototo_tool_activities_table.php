<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tool IN/OUT history. Every foreign key is RESTRICT, so neither a tool, a board nor a user
        // can be deleted while history points at it.
        Schema::create('lototo_tool_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('lototo_tools')->restrictOnDelete();   // bigint unsigned = lototo_tools.id
            $table->foreignId('board_id')->constrained('lototo_boards')->restrictOnDelete(); // bigint unsigned = lototo_boards.id
            $table->string('location', 255);
            $table->string('status', 20)->default('in_progress'); // in_progress = IN, finished = OUT
            $this->addReferencedIdColumn($table, 'started_by', 'users');
            $table->foreign('started_by')->references('id')->on('users')->restrictOnDelete();
            $this->addReferencedIdColumn($table, 'ended_by', 'users', nullable: true);
            $table->foreign('ended_by')->references('id')->on('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            // Equals tool_id while IN and NULL once OUT; the unique index lets the database itself reject
            // a second open IN for the same tool (NULLs never collide).
            $table->unsignedBigInteger('open_tool_id')->nullable()
                ->storedAs("case when status = 'in_progress' then tool_id end");
            $table->timestamps();

            $table->unique('open_tool_id', 'lototo_tool_activities_one_open_per_tool');
            $table->index(['tool_id', 'started_at']);
            $table->index(['board_id', 'status']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lototo_tool_activities')) {
            return;
        }

        // History is never dropped by a rollback.
        if (DB::table('lototo_tool_activities')->exists()) {
            throw new RuntimeException('LOTOTO tool history exists. Export lototo_tool_activities before rolling back; rollback refused.');
        }

        Schema::drop('lototo_tool_activities');
    }

    private function addReferencedIdColumn(Blueprint $table, string $column, string $referencedTable, bool $nullable = false): void
    {
        $id = collect(Schema::getColumns($referencedTable))->firstWhere('name', 'id');
        if (! $id) {
            throw new RuntimeException("Could not inspect {$referencedTable}.id before creating its foreign key.");
        }

        $type = strtolower((string) $id['type']);
        if (str_starts_with($type, 'bigint')) {
            $definition = $table->bigInteger($column);
        } elseif (str_starts_with($type, 'int')) {
            $definition = $table->integer($column);
        } else {
            throw new RuntimeException("Unsupported {$referencedTable}.id type: {$type}.");
        }

        if (str_contains($type, 'unsigned')) {
            $definition->unsigned();
        }
        if ($nullable) {
            $definition->nullable();
        }
    }
};
