<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_comms', function (Blueprint $table) {
            // Existing entries predate the question, so both stay null for them.
            $table->boolean('is_torsion_shaft')->nullable()->after('problem');
            $table->string('torsion_relay_status', 30)->nullable()->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('shift_comms', function (Blueprint $table) {
            $table->dropColumn(['is_torsion_shaft', 'torsion_relay_status']);
        });
    }
};
