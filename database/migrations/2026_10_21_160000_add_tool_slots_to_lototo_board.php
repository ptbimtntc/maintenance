<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lototo_board_slots', 'tool_label')) {
            Schema::table('lototo_board_slots', function (Blueprint $table) {
                $table->string('tool_label', 100)->nullable()->after('spare_lock_id');
            });
        }

        $now = now();

        for ($position = 1; $position <= 15; $position++) {
            $existing = DB::table('lototo_board_slots')
                ->where('category', 'tool')
                ->where('position', $position)
                ->first();

            if ($existing) {
                if (blank($existing->tool_label)) {
                    DB::table('lototo_board_slots')->where('id', $existing->id)->update([
                        'tool_label' => 'Tool '.$position,
                        'updated_at' => $now,
                    ]);
                }

                continue;
            }

            DB::table('lototo_board_slots')->insert([
                'category' => 'tool',
                'position' => $position,
                'tool_label' => 'Tool '.$position,
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('lototo_board_slots')->where('category', 'tool')->exists()) {
            throw new RuntimeException('LOTOTO tool slots contain board configuration. Export or migrate those slots before rollback.');
        }

        Schema::table('lototo_board_slots', function (Blueprint $table) {
            $table->dropColumn('tool_label');
        });
    }
};
