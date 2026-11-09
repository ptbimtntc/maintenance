<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Activities store lototo_boards.code (up to 80 characters). Boards created by Add Board had 38-character
        // codes, which strict MySQL rejects in the original 30-character column, so IN failed on those boards.
        Schema::table('lototo_activities', function (Blueprint $table) {
            $table->string('board', 80)->change();
        });

        // Spare lock codes are unique per board (as SpareLockController validates and the form looks them up),
        // so each division can number its own spare locks SP-01, SP-02, ... without colliding with another board.
        $indexes = collect(Schema::getIndexes('spare_locks'))->pluck('name');
        Schema::table('spare_locks', function (Blueprint $table) use ($indexes) {
            if (! $indexes->contains('spare_locks_board_code_unique')) {
                $table->unique(['board_id', 'code'], 'spare_locks_board_code_unique');
            }
            if ($indexes->contains('spare_locks_code_unique')) {
                $table->dropUnique('spare_locks_code_unique');
            }
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('spare_locks'));
        $hasSharedCodes = DB::table('spare_locks')->select('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->exists();
        $indexNames = $indexes->pluck('name');
        if (! $hasSharedCodes && ! $indexNames->contains('spare_locks_code_unique')) {
            Schema::table('spare_locks', function (Blueprint $table) {
                $table->unique('code');
            });
        }

        if (! $hasSharedCodes && $indexNames->contains('spare_locks_board_code_unique')) {
            $hasBoardIdIndex = $indexes->contains(
                fn (array $index) => ($index['columns'][0] ?? null) === 'board_id'
                    && $index['name'] !== 'spare_locks_board_code_unique'
            );
            if (! $hasBoardIdIndex) {
                Schema::table('spare_locks', function (Blueprint $table) {
                    $table->index('board_id', 'spare_locks_board_id_index');
                });
            }

            Schema::table('spare_locks', function (Blueprint $table) {
                $table->dropUnique('spare_locks_board_code_unique');
            });
        }

        $boardCodes = DB::table('lototo_activities')->distinct()->pluck('board');
        if (! $boardCodes->contains(fn ($code) => mb_strlen((string) $code) > 30)) {
            Schema::table('lototo_activities', function (Blueprint $table) {
                $table->string('board', 30)->change();
            });
        }
    }
};
