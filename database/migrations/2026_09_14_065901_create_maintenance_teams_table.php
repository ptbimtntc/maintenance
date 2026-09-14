<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique()->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['maintenance_area_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_teams');
    }
};
