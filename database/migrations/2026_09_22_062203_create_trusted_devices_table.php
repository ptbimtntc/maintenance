<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A device an employee has explicitly enrolled Face Login on. The
     * cookie the browser holds only ever carries a random token; this row
     * is what maps that token to a specific user, and it's what makes face
     * matching 1-to-1 (this device's owner) instead of 1-to-many (every
     * enrolled employee) - far fewer false-accepts, and a device stops
     * working for face login the moment its row is deleted.
     */
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('label')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
