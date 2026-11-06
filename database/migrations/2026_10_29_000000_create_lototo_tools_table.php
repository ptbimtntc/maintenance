<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Physical tools owned by one board, like spare_locks. The IN/OUT state lives only in
        // lototo_tool_activities, so this table has no pointer back to an activity.
        Schema::create('lototo_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('lototo_boards')->restrictOnDelete(); // bigint unsigned = lototo_boards.id
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('location', 255)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['board_id', 'code'], 'lototo_tools_board_code_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lototo_tools')) {
            return;
        }

        // Only tools that the mapping can recreate exactly may be dropped; anything an admin added
        // (location, image, deactivation) would be lost, so refuse instead.
        $hasAdminData = DB::table('lototo_tools')
            ->where(fn ($tools) => $tools->whereNotNull('location')->orWhereNotNull('image_path')->orWhere('is_active', false))
            ->exists();
        if ($hasAdminData) {
            throw new RuntimeException('lototo_tools contains location, image or inactive tools. Export them before rolling back; rollback refused.');
        }

        Schema::drop('lototo_tools');
    }
};
