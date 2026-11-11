<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_comms', function (Blueprint $table) {
            $table->string('machine_type', 50)->nullable()->after('machine_no');
        });
    }

    public function down(): void
    {
        Schema::table('shift_comms', function (Blueprint $table) {
            $table->dropColumn('machine_type');
        });
    }
};

