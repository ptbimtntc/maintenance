<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lototo_board_slots', 'tool_column_span')) {
            Schema::table('lototo_board_slots', function (Blueprint $table) {
                $table->unsignedTinyInteger('tool_column_span')->default(1)->after('tool_label');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lototo_board_slots', 'tool_column_span')) {
            Schema::table('lototo_board_slots', function (Blueprint $table) {
                $table->dropColumn('tool_column_span');
            });
        }
    }
};
