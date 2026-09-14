<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique()->nullable();
            $table->text('description')->nullable();
            // Marks whether employees with this status should be counted as
            // currently active in dashboards and reports (e.g. "Active" = true,
            // "Resigned" / "Terminated" = false). Kept configurable instead of
            // hard-coding status names throughout the application.
            $table->boolean('counts_as_active')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_statuses');
    }
};
