<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->string('unit', 20)->default('CB')->after('machine_type');
            $table->dropUnique(['machine_no']);
            $table->unique(['unit', 'machine_no']);
        });

        DB::table('machines')->whereNull('unit')->orWhere('unit', '')->update(['unit' => 'CB']);
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropUnique(['unit', 'machine_no']);
            $table->unique(['machine_no']);
            $table->dropColumn('unit');
        });
    }
};
