<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->foreignId('trainer_signatory_id')->nullable()->after('trainer_signature_path')->constrained('signatories')->nullOnDelete();
            $table->foreignId('authorizer_signatory_id')->nullable()->after('authorizer_signature_path')->constrained('signatories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trainer_signatory_id');
            $table->dropConstrainedForeignId('authorizer_signatory_id');
        });
    }
};
