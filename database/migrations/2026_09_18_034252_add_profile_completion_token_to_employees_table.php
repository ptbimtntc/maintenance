<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('profile_completion_token')->nullable()->unique()->after('sap_id');
            $table->timestamp('profile_completion_expires_at')->nullable()->after('profile_completion_token');
            $table->timestamp('profile_completed_at')->nullable()->after('profile_completion_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['profile_completion_token']);
            $table->dropColumn(['profile_completion_token', 'profile_completion_expires_at', 'profile_completed_at']);
        });
    }
};
