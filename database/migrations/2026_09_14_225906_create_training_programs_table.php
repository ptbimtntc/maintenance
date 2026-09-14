<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code')->unique()->nullable();
            $table->foreignId('training_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_type_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->string('target_audience')->nullable();
            $table->boolean('is_internal')->default(true);
            $table->string('trainer_name')->nullable();
            $table->foreignId('training_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('duration_value', 6, 1)->nullable();
            $table->string('duration_unit')->nullable(); // hours | days
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->string('budget_reference')->nullable();
            $table->string('status')->default('draft'); // draft | active | inactive
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_programs');
    }
};
