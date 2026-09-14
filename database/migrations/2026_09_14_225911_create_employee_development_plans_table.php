<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_development_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('development_objective');
            $table->foreignId('related_skill_id')->nullable()->constrained('skills')->nullOnDelete();
            $table->foreignId('current_competency_level_id')->nullable()->constrained('competency_levels')->nullOnDelete();
            $table->foreignId('target_competency_level_id')->nullable()->constrained('competency_levels')->nullOnDelete();
            $table->string('development_action'); // formal_training | ojt | coaching | mentoring | job_rotation | self_learning | practical_assessment | cross_training | certification
            $table->foreignId('recommended_training_program_id')->nullable()
                ->constrained('training_programs', 'id', 'edp_recommended_training_program_foreign')
                ->nullOnDelete();
            $table->foreignId('mentor_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('target_completion_date')->nullable();
            $table->string('priority')->default('medium'); // low | medium | high
            $table->string('status')->default('not_started'); // not_started | in_progress | completed | on_hold
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->date('review_date')->nullable();
            $table->text('manager_remarks')->nullable();
            $table->text('employee_remarks')->nullable();
            $table->date('completion_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_development_plans');
    }
};
