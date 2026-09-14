<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('code')->unique()->nullable();
            $table->unsignedTinyInteger('grade_level')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['department_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
