<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expiry-based status (valid / expiring soon / expired / no expiry) is
     * deliberately NOT stored - it's derived from expiry_date on read, so it
     * can never go stale. Only verification_status is a real stored state.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('certificate_number')->nullable();
            $table->string('issuing_organization')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('related_skill_id')->nullable()->constrained('skills')->nullOnDelete();
            $table->foreignId('related_training_program_id')->nullable()->constrained('training_programs')->nullOnDelete();
            $table->string('verification_status')->default('verified'); // pending_verification | verified
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('previous_certificate_id')->nullable()->constrained('certificates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
