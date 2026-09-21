<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->unsignedSmallInteger('validity_months')->nullable()->after('trainer_name');
            $table->unsignedTinyInteger('passing_score')->nullable()->after('validity_months');
            $table->string('authorizer_name')->nullable()->after('passing_score');
            $table->string('authorizer_title')->nullable()->after('authorizer_name');
        });

        Schema::create('training_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->boolean('allow_multiple_answers')->default(false);
            $table->timestamps();
        });

        Schema::create('training_question_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_question_id')->constrained()->cascadeOnDelete();
            $table->string('option_label', 1);
            $table->string('choice_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('points')->default(1);
            $table->timestamps();
        });

        Schema::table('training_participants', function (Blueprint $table) {
            $table->timestamp('quiz_submitted_at')->nullable();
            $table->unsignedTinyInteger('quiz_score')->nullable();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('training_quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_question_choice_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_quiz_answers');

        Schema::table('training_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificate_id');
            $table->dropColumn(['quiz_submitted_at', 'quiz_score']);
        });

        Schema::dropIfExists('training_question_choices');
        Schema::dropIfExists('training_questions');

        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropColumn(['validity_months', 'passing_score', 'authorizer_name', 'authorizer_title']);
        });
    }
};
