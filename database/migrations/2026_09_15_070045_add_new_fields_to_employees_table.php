<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('business_unit_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->foreignId('skill_position_id')->nullable()->after('position_id')->constrained()->nullOnDelete();
            $table->foreignId('employment_source_id')->nullable()->after('employment_type_id')->constrained()->nullOnDelete();
            $table->string('workforce_category')->nullable()->after('employment_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
            $table->dropConstrainedForeignId('skill_position_id');
            $table->dropConstrainedForeignId('employment_source_id');
            $table->dropColumn('workforce_category');
        });
    }
};
