<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A slot keeps its position number when Edit Mode changes its category, so a category may now hold two slots
 * with the same number (one moved in from another category). Where a slot sits is guarded by the cell index
 * lototo_slots_board_category_cell_unique (previous migration); the number is only the slot's identity label.
 * The unique (board, category, position) index becomes a plain index for the same lookups. No row is touched.
 */
return new class extends Migration
{
    private const UNIQUE = 'lototo_slots_board_category_position_unique';

    private const INDEX = 'lototo_slots_board_category_position_index';

    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('lototo_board_slots'))->pluck('name');
        // The plain index comes first, so board_id lookups (and its foreign key) are never without an index.
        if (! $indexes->contains(self::INDEX)) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->index(['board_id', 'category', 'position'], self::INDEX));
        }
        if ($indexes->contains(self::UNIQUE)) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->dropUnique(self::UNIQUE));
        }
    }

    public function down(): void
    {
        // The unique index can only come back while no category repeats a number; renumbering is never done here.
        $repeated = DB::table('lototo_board_slots')
            ->select('board_id', 'category', 'position')
            ->groupBy('board_id', 'category', 'position')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        if ($repeated > 0) {
            throw new RuntimeException("{$repeated} LOTOTO slot number(s) repeat within a category (slots moved by Edit Mode). Rollback refused; nothing was renumbered.");
        }

        $indexes = collect(Schema::getIndexes('lototo_board_slots'))->pluck('name');
        if (! $indexes->contains(self::UNIQUE)) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->unique(['board_id', 'category', 'position'], self::UNIQUE));
        }
        if ($indexes->contains(self::INDEX)) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->dropIndex(self::INDEX));
        }
    }
};
