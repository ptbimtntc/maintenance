<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lototo_board_slots', 'tool_id')) {
            Schema::table('lototo_board_slots', function (Blueprint $table) {
                // bigint unsigned = lototo_tools.id; tool_label is kept untouched for display fallback and rollback.
                $table->foreignId('tool_id')->nullable()->after('tool_label')
                    ->constrained('lototo_tools')->restrictOnDelete();
                $table->unique(['board_id', 'category', 'tool_id'], 'lototo_slots_board_category_tool_unique');
            });
        }

        // One tool per labelled slot. Only tool_id is written on the slot: position, tool_label,
        // tool_column_span and is_enabled stay exactly as they are. Blank labels (shown as empty on the
        // board, e.g. all 15 slots of board 4) are skipped, so those slots stay empty.
        // Idempotent: re-running after a failure only maps slots that still have no tool_id.
        // Slots without a board (seeded on installs that never had the legacy board) are shown nowhere and skipped.
        DB::transaction(function () {
            $now = now();
            DB::table('lototo_board_slots')
                ->where('category', 'tool')
                ->whereNotNull('board_id')
                ->whereNull('tool_id')
                ->whereNotNull('tool_label')
                ->whereRaw("trim(tool_label) <> ''")
                ->orderBy('board_id')->orderBy('position')
                ->lockForUpdate()
                ->get(['id', 'board_id', 'position', 'tool_label'])
                ->each(function ($slot) use ($now) {
                    $toolId = DB::table('lototo_tools')->insertGetId([
                        'board_id' => $slot->board_id,
                        'code' => sprintf('TL-%02d', $slot->position),
                        'name' => $slot->tool_label,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('lototo_board_slots')->where('id', $slot->id)->update(['tool_id' => $toolId]);
                });
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lototo_board_slots', 'tool_id')) {
            return;
        }

        // Refuse unless every tool can be represented again by a plain slot label: no history,
        // linked to exactly one slot, and still carrying the code the mapping gave it.
        if (Schema::hasTable('lototo_tool_activities') && DB::table('lototo_tool_activities')->exists()) {
            throw new RuntimeException('LOTOTO tool history exists; rollback refused.');
        }
        $unrepresentable = DB::table('lototo_tools')
            ->leftJoin('lototo_board_slots', 'lototo_board_slots.tool_id', '=', 'lototo_tools.id')
            ->get(['lototo_tools.code', 'lototo_board_slots.id as slot_id', 'lototo_board_slots.position'])
            ->contains(fn ($tool) => $tool->slot_id === null || $tool->code !== sprintf('TL-%02d', $tool->position));
        if ($unrepresentable) {
            throw new RuntimeException('Some LOTOTO tools are not linked to a slot or have edited codes. Export them before rolling back; rollback refused.');
        }

        DB::transaction(function () {
            // Keep what the board shows: the current tool name becomes the slot label again.
            DB::table('lototo_board_slots')->whereNotNull('tool_id')->orderBy('id')->get(['id', 'tool_id'])
                ->each(fn ($slot) => DB::table('lototo_board_slots')->where('id', $slot->id)->update([
                    'tool_label' => DB::table('lototo_tools')->where('id', $slot->tool_id)->value('name'),
                ]));
        });

        Schema::table('lototo_board_slots', function (Blueprint $table) {
            $table->dropUnique('lototo_slots_board_category_tool_unique');
            $table->dropConstrainedForeignId('tool_id');
        });
        // Uploaded images under storage/app/private/lototo-tools are never deleted by a rollback.
    }
};
