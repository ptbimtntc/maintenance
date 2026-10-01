<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_comms', function (Blueprint $table) {
            $table->unsignedInteger('length_m')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shift_comms', function (Blueprint $table) {
            $table->decimal('length_m', 10, 2)->nullable()->change();
        });
    }
};
