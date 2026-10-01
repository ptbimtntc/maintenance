<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_comms', function (Blueprint $table) {
            $table->id();
            // YYMMDD + shift digit + 2-digit running number, e.g. 261001101.
            $table->string('comm_number', 12)->unique();
            $table->date('comm_date');
            $table->unsignedTinyInteger('shift');
            $table->string('team', 10);
            $table->string('machine_no', 50);
            $table->text('problem');
            $table->text('progress')->nullable();
            $table->string('construction', 150)->nullable();
            $table->decimal('length_m', 10, 2)->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['comm_date', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_comms');
    }
};
