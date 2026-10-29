<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lototo_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('lototo_activities', 'supervisor_follow_up')) {
                $table->text('supervisor_follow_up')->nullable();
            }

            if (! Schema::hasColumn('lototo_activities', 'supervisor_follow_up_by')) {
                $table->integer('supervisor_follow_up_by')->nullable();
            } else {
                $table->integer('supervisor_follow_up_by')->nullable()->change();
            }

            if (! Schema::hasColumn('lototo_activities', 'supervisor_follow_up_at')) {
                $table->timestamp('supervisor_follow_up_at')->nullable();
            }

            if (! Schema::hasColumn('lototo_activities', 'supervisor_follow_up_status')) {
                $table->string('supervisor_follow_up_status', 20)->default('not_required');
            }
        });

        $hasSupervisorForeignKey = collect(Schema::getForeignKeys('lototo_activities'))
            ->contains(fn (array $foreignKey) => in_array('supervisor_follow_up_by', $foreignKey['columns'], true));

        if (! $hasSupervisorForeignKey) {
            Schema::table('lototo_activities', function (Blueprint $table) use ($hasSupervisorForeignKey) {
                $table->foreign('supervisor_follow_up_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $hasSupervisorForeignKey = collect(Schema::getForeignKeys('lototo_activities'))
            ->contains(fn (array $foreignKey) => in_array('supervisor_follow_up_by', $foreignKey['columns'], true));

        Schema::table('lototo_activities', function (Blueprint $table) {
            if ($hasSupervisorForeignKey) {
                $table->dropForeign(['supervisor_follow_up_by']);
            }

            $table->dropColumn([
                'supervisor_follow_up',
                'supervisor_follow_up_at',
                'supervisor_follow_up_status',
                'supervisor_follow_up_by',
            ]);
        });
    }
};