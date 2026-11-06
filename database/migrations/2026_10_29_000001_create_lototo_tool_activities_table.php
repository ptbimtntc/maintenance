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
            $table->integer('started_by');                        // int (signed) = users.id
            $table->foreign('started_by')->references('id')->on('users')->restrictOnDelete();
            $table->integer('ended_by')->nullable();
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
};
