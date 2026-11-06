<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores where each board slot is drawn (grid_row, grid_column inside its category's section), apart from its
 * position number, which stays the slot's identity. The backfill writes exactly the cell the board view
 * computes today, so every board looks the same before and after. Only these two columns are written: no
 * position, assignment, lock, tool or activity changes. The placement rules are copied here on purpose so later
 * changes to the board view cannot change what this migration wrote.
 */
return new class extends Migration
{
    private const INDEX = 'lototo_slots_board_category_cell_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('lototo_board_slots', 'grid_row')) {
            Schema::table('lototo_board_slots', function (Blueprint $table) {
                $table->unsignedSmallInteger('grid_row')->nullable()->after('position');
                $table->unsignedSmallInteger('grid_column')->nullable()->after('grid_row');
            });
        }

        // Idempotent: a re-run after a failure fills slots that still have no cell. Slots without a board are
        // shown nowhere and keep no cell.
        DB::table('lototo_boards')->orderBy('id')->get(['id', 'layout'])->each(function ($board) {
            DB::transaction(function () use ($board) {
                $slots = DB::table('lototo_board_slots')->where('board_id', $board->id)->lockForUpdate()
                    ->get(['id', 'category', 'position', 'tool_column_span', 'grid_row', 'grid_column']);
                foreach ($this->expectedCells($board, $slots) as $slotId => [$row, $column]) {
                    $slot = $slots->firstWhere('id', $slotId);
                    if ($slot->grid_row === null || $slot->grid_column === null) {
                        DB::table('lototo_board_slots')->where('id', $slotId)->update(['grid_row' => $row, 'grid_column' => $column]);
                    }
                }
            });
        });

        if (! collect(Schema::getIndexes('lototo_board_slots'))->pluck('name')->contains(self::INDEX)) {
            Schema::table('lototo_board_slots', function (Blueprint $table) {
                $table->unique(['board_id', 'category', 'grid_row', 'grid_column'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lototo_board_slots', 'grid_row')) {
            return;
        }

        // Dropping the cells is safe only while every slot still sits where its position number puts it. A slot
        // placed elsewhere (moved category, kept cell, removed column) would silently jump on the board.
        $moved = DB::table('lototo_boards')->orderBy('id')->get(['id', 'layout'])->sum(function ($board) {
            $slots = DB::table('lototo_board_slots')->where('board_id', $board->id)
                ->get(['id', 'category', 'position', 'tool_column_span', 'grid_row', 'grid_column']);
            $expected = $this->expectedCells($board, $slots);

            return $slots->filter(fn ($slot) => $slot->grid_row !== null
                && [(int) $slot->grid_row, (int) $slot->grid_column] !== ($expected[$slot->id] ?? null))->count();
        });
        if ($moved > 0) {
            throw new RuntimeException("{$moved} LOTOTO slot(s) are placed away from their position number; rolling back would move them on the board. Rollback refused.");
        }

        if (collect(Schema::getIndexes('lototo_board_slots'))->pluck('name')->contains(self::INDEX)) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->dropUnique(self::INDEX));
        }
        Schema::table('lototo_board_slots', function (Blueprint $table) {
            $table->dropColumn(['grid_row', 'grid_column']);
        });
    }

    /** [slot id => [row, column]] as the board view placed slots when this migration was written. */
    private function expectedCells(object $board, Collection $slots): array
    {
        $layout = json_decode($board->layout ?? 'null', true) ?: [];
        $boardColumns = (int) ($layout['board_columns'] ?? $layout['technician_columns'] ?? 20);
        $cells = [];

        foreach ($slots->groupBy('category') as $category => $categorySlots) {
            $columns = min(30, max(5, (int) ($layout[$category.'_columns'] ?? $boardColumns)));
            $byPosition = $categorySlots->keyBy(fn ($slot) => (int) $slot->position);

            if ($category !== 'tool') {
                foreach ($byPosition as $position => $slot) {
                    $cells[$slot->id] = [intdiv($position - 1, $columns) + 1, (($position - 1) % $columns) + 1];
                }

                continue;
            }

            // Other / Tools flow in position order; a missing position takes one column.
            $row = 1;
            $column = 1;
            foreach (range(1, max(1, (int) $byPosition->keys()->max())) as $position) {
                $slot = $byPosition->get($position);
                $span = max(1, min(3, (int) ($slot?->tool_column_span ?? 1)));
                if ($column + $span - 1 > $columns) {
                    $row++;
                    $column = 1;
                }
                if ($slot) {
                    $cells[$slot->id] = [$row, $column];
                }
                $column += $span;
                if ($column > $columns) {
                    $row++;
                    $column = 1;
                }
            }
        }

        return $cells;
    }
};
