<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separates where a slot is drawn (section, with grid_row / grid_column inside it) from what it holds (category).
 * Changing a slot's category in Edit Mode then keeps it in its band and cell. The backfill sets section = category,
 * so every board looks exactly as before, and the unique cell index moves from category to section. Only the new
 * column is written: no number, cell, assignment, lock, tool or activity changes.
 */
return new class extends Migration
{
    private const CATEGORY_CELL = 'lototo_slots_board_category_cell_unique';

    private const SECTION_CELL = 'lototo_slots_board_section_cell_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('lototo_board_slots', 'section')) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->string('section', 20)->nullable()->after('category'));
        }
        // Idempotent: only rows without a section get one.
        DB::table('lototo_board_slots')->whereNull('section')->update(['section' => DB::raw('category')]);

        $indexes = collect(Schema::getIndexes('lototo_board_slots'))->pluck('name');
        // The section index comes first, so cells are never unguarded.
        if (! $indexes->contains(self::SECTION_CELL)) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->unique(['board_id', 'section', 'grid_row', 'grid_column'], self::SECTION_CELL));
        }
        if ($indexes->contains(self::CATEGORY_CELL)) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->dropUnique(self::CATEGORY_CELL));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lototo_board_slots', 'section')) {
            return;
        }
        // Without the column a slot is drawn in its category's band again: refuse while any slot sits in another band.
        $elsewhere = DB::table('lototo_board_slots')->whereNotNull('section')->whereColumn('section', '!=', 'category')->count();
        if ($elsewhere > 0) {
            throw new RuntimeException("{$elsewhere} LOTOTO slot(s) sit in a band other than their category; rolling back would move them on the board. Rollback refused.");
        }

        $indexes = collect(Schema::getIndexes('lototo_board_slots'))->pluck('name');
        if (! $indexes->contains(self::CATEGORY_CELL)) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->unique(['board_id', 'category', 'grid_row', 'grid_column'], self::CATEGORY_CELL));
        }
        if ($indexes->contains(self::SECTION_CELL)) {
            Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->dropUnique(self::SECTION_CELL));
        }
        Schema::table('lototo_board_slots', fn (Blueprint $table) => $table->dropColumn('section'));
    }
};
