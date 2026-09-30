<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->text('remarks');
            $table->string('compensation_type');

            // locked (default, just logged - correct as entered): edit_requested
            // (supervisor asked to fix a mistake): edit_approved (HR/Admin
            // unlocked it for one edit, then it goes back to locked).
            $table->string('status')->default('locked');
            $table->text('edit_request_reason')->nullable();
            $table->timestamp('edit_requested_at')->nullable();
            $table->foreignId('edit_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edit_approved_at')->nullable();

            // Set once HR/Admin has exported this entry and emailed it out -
            // supervisors can see it's already been sent, HR won't re-send it.
            $table->timestamp('submitted_to_hr_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_entries');
    }
};
