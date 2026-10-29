<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lototo_activities', function (Blueprint $table) {
            $table->id();
            $table->integer('employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->restrictOnDelete();
            $table->foreignId('spare_lock_id')->nullable()->constrained()->nullOnDelete();
            $table->string('board', 30);
            $table->string('location');
            $table->text('activity');
            $table->json('risks');
            $table->string('other_risk')->nullable();
            $table->text('risk_controls');
            $table->boolean('knows_procedure');
            $table->boolean('follows_lototo');
            $table->boolean('uses_ppe');
            $table->boolean('safe_tools');
            $table->boolean('safe_hands');
            $table->text('supervisor_feedback')->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->string('outcome', 20)->nullable();
            $table->json('out_details')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['spare_lock_id', 'started_at']);
        });

        Schema::table('spare_locks', function (Blueprint $table) {
            $table->foreignId('current_activity_id')
                ->nullable()
                ->after('is_active')
                ->constrained('lototo_activities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spare_locks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_activity_id');
        });

        Schema::dropIfExists('lototo_activities');
    }
};
