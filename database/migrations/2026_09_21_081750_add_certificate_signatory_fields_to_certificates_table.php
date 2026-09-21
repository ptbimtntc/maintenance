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
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('trainer_name')->nullable()->after('issuing_organization');
            $table->string('authorizer_name')->nullable()->after('trainer_name');
            $table->string('authorizer_title')->nullable()->after('authorizer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['trainer_name', 'authorizer_name', 'authorizer_title']);
        });
    }
};
