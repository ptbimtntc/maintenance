<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Version history is kept as multiple rows per position (incrementing
     * "version"), with the previous row's status flipped to "archived" when
     * a new version is created - rather than a separate versions table.
     */
    public function up(): void
    {
        Schema::create('job_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reports_to_position_id')->nullable()->constrained('positions')->nullOnDelete();

            $table->string('job_title');
            $table->text('job_purpose')->nullable();
            $table->text('main_responsibilities')->nullable();
            $table->text('detailed_duties')->nullable();
            $table->string('required_education')->nullable();
            $table->string('required_experience')->nullable();
            $table->text('required_technical_skills')->nullable();
            $table->text('required_soft_skills')->nullable();
            $table->text('required_certifications')->nullable();
            $table->text('safety_responsibilities')->nullable();
            $table->string('direct_reports_summary')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->date('effective_date')->nullable();
            $table->date('review_date')->nullable();
            $table->string('status')->default('draft'); // draft | pending_review | active | archived

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('approval_date')->nullable();

            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['position_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_descriptions');
    }
};
