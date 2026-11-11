<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every supervisor reply to a LOTOTO follow-up, kept as history: a later reply never overwrites an earlier one.
 * lototo_activities.supervisor_follow_up / _by / _at keep holding the latest reply, so existing pages keep working.
 * Replies saved before this table existed are copied in once. Foreign keys take the type of the id they point to
 * (BIGINT UNSIGNED on the hosting schema, INT on an older local one); deleting an activity with replies is refused.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lototo_supervisor_feedbacks')) {
            Schema::create('lototo_supervisor_feedbacks', function (Blueprint $table) {
                $table->id();
                $this->referenceColumn($table, 'lototo_activity_id', 'lototo_activities');
                $this->referenceColumn($table, 'supervisor_employee_id', 'employees', nullable: true);
                $this->referenceColumn($table, 'user_id', 'users', nullable: true);
                $table->text('comment');
                $table->timestamps();

                $table->foreign('lototo_activity_id')->references('id')->on('lototo_activities')->restrictOnDelete();
                $table->foreign('supervisor_employee_id')->references('id')->on('employees')->nullOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['lototo_activity_id', 'created_at'], 'lototo_supervisor_feedbacks_activity_index');
            });
        }

        // Replies saved before: one history row each (only once, for activities that have none yet).
        DB::table('lototo_activities')
            ->whereNotNull('supervisor_follow_up')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('lototo_supervisor_feedbacks')
                ->whereColumn('lototo_supervisor_feedbacks.lototo_activity_id', 'lototo_activities.id'))
            ->orderBy('id')
            ->get(['id', 'supervisor_follow_up', 'supervisor_follow_up_by', 'supervisor_follow_up_at'])
            ->each(function ($activity) {
                $at = $activity->supervisor_follow_up_at ?? now('UTC');
                DB::table('lototo_supervisor_feedbacks')->insert([
                    'lototo_activity_id' => $activity->id,
                    'supervisor_employee_id' => $activity->supervisor_follow_up_by
                        ? DB::table('employees')->where('user_id', $activity->supervisor_follow_up_by)->value('id')
                        : null,
                    'user_id' => $activity->supervisor_follow_up_by,
                    'comment' => $activity->supervisor_follow_up,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lototo_supervisor_feedbacks')) {
            return;
        }
        // The latest reply per activity is still in lototo_activities; only earlier ones live here alone.
        $history = DB::table('lototo_supervisor_feedbacks')->select('lototo_activity_id')->groupBy('lototo_activity_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($history) {
            throw new RuntimeException('Supervisor feedback history would be lost (activities with more than one reply). Export lototo_supervisor_feedbacks before rolling back; rollback refused.');
        }
        Schema::drop('lototo_supervisor_feedbacks');
    }

    private function referenceColumn(Blueprint $table, string $column, string $referencedTable, bool $nullable = false): void
    {
        $id = collect(Schema::getColumns($referencedTable))->firstWhere('name', 'id');
        if (! $id) {
            throw new RuntimeException("Could not inspect {$referencedTable}.id before creating its foreign key.");
        }
        $type = strtolower((string) $id['type']);
        $definition = str_starts_with($type, 'bigint') ? $table->bigInteger($column) : $table->integer($column);
        if (str_contains($type, 'unsigned')) {
            $definition->unsigned();
        }
        if ($nullable) {
            $definition->nullable();
        }
    }
};
