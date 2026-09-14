<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_session_id')->nullable()->constrained()->nullOnDelete();
            $table->date('training_date');
            $table->foreignId('training_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trainer_name')->nullable();
            $table->decimal('duration_hours', 6, 1)->nullable();
            $table->string('attendance_status')->default('attended'); // attended | absent | excused
            $table->string('completion_status')->default('completed'); // completed | incomplete | failed
            $table->decimal('assessment_score', 5, 2)->nullable();
            $table->string('assessment_result')->nullable(); // pass | fail
            $table->foreignId('competency_before_level_id')->nullable()->constrained('competency_levels')->nullOnDelete();
            $table->foreignId('competency_after_level_id')->nullable()->constrained('competency_levels')->nullOnDelete();
            $table->boolean('certificate_issued')->default(false);
            $table->string('certificate_reference')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('record_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_records');
    }
};
