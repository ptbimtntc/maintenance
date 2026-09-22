<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A 128-float face descriptor (see resources/js/face-login.js),
            // never the photo itself - the descriptor can't be reversed
            // back into an image, only compared for similarity.
            $table->text('face_descriptor')->nullable()->after('password');
            $table->timestamp('face_enrolled_at')->nullable()->after('face_descriptor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['face_descriptor', 'face_enrolled_at']);
        });
    }
};
