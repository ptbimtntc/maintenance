<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only assessment log: each row is one assessment event. The
     * employee's "current" level for a skill is simply the most recent row
     * for that (employee_id, skill_id) pair - this gives assessment history
     * for free without a separate history table.
     */
    public function up(): void
    {
        Schema::create('employee_skill_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_level_id')->constrained('competency_levels')->cascadeOnDelete();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assessment_date');
            $table->string('assessment_method')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'skill_id', 'assessment_date'], 'employee_skill_assessments_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_skill_assessments');
    }
};
