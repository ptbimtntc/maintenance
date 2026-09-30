<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskPlan;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staffWithEmployee(): array
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::MaintenanceStaff->value);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        return [$user, $employee];
    }

    private function makePlan(User $owner): TaskPlan
    {
        $this->actingAs($owner)->post(route('tasks.plans.store'), ['name' => 'Shutdown Prep'])->assertRedirect();

        return TaskPlan::where('name', 'Shutdown Prep')->firstOrFail();
    }

    public function test_any_employee_can_create_a_plan_with_default_buckets(): void
    {
        [$user] = $this->staffWithEmployee();

        $plan = $this->makePlan($user);

        $this->assertSame(['To do', 'In progress', 'Done'], $plan->buckets->pluck('name')->all());
        $this->assertSame($user->id, $plan->owner_id);
    }

    public function test_guest_cannot_use_the_planner(): void
    {
        $guest = User::factory()->create();
        $guest->assignRole(RoleName::Guest->value);

        $this->actingAs($guest)->get(route('tasks.index'))->assertForbidden();
    }

    public function test_assigning_a_task_notifies_the_assignee_but_not_the_assigner(): void
    {
        Notification::fake();
        [$owner, $ownerEmployee] = $this->staffWithEmployee();
        [$assignee, $assigneeEmployee] = $this->staffWithEmployee();
        $plan = $this->makePlan($owner);
        $bucket = $plan->buckets->first();

        $this->actingAs($owner)->post(route('tasks.store', $plan), ['task_bucket_id' => $bucket->id, 'title' => 'Lubricate press'])->assertRedirect();
        $task = Task::firstOrFail();

        $this->actingAs($owner)->put(route('tasks.update', $task), [
            'title' => 'Lubricate press',
            'task_bucket_id' => $bucket->id,
            'priority' => 'high',
            'progress' => 'in_progress',
            'assignee_ids' => [$assigneeEmployee->id, $ownerEmployee->id],
        ])->assertRedirect();

        Notification::assertSentTo($assignee, TaskAssigned::class);
        Notification::assertNotSentTo($owner, TaskAssigned::class);
        $this->assertCount(2, $task->fresh()->assignees);
    }

    public function test_assignee_can_open_the_plan_but_an_unrelated_user_cannot(): void
    {
        [$owner] = $this->staffWithEmployee();
        [$assignee, $assigneeEmployee] = $this->staffWithEmployee();
        [$outsider] = $this->staffWithEmployee();
        $plan = $this->makePlan($owner);
        $task = $plan->tasks()->create(['task_bucket_id' => $plan->buckets->first()->id, 'title' => 'T', 'created_by' => $owner->id]);
        $task->assignees()->attach($assigneeEmployee->id);

        $this->actingAs($assignee)->get(route('tasks.plans.show', $plan))->assertOk();
        $this->actingAs($outsider)->get(route('tasks.plans.show', $plan))->assertForbidden();
        $this->actingAs($outsider)->get(route('tasks.show', $task))->assertForbidden();
    }

    public function test_only_the_owner_can_manage_buckets(): void
    {
        [$owner] = $this->staffWithEmployee();
        [$assignee, $assigneeEmployee] = $this->staffWithEmployee();
        $plan = $this->makePlan($owner);
        $plan->tasks()->create(['task_bucket_id' => $plan->buckets->first()->id, 'title' => 'T', 'created_by' => $owner->id])
            ->assignees()->attach($assigneeEmployee->id);

        $this->actingAs($assignee)->post(route('tasks.plans.buckets.store', $plan), ['name' => 'Blocked'])->assertForbidden();
        $this->actingAs($owner)->post(route('tasks.plans.buckets.store', $plan), ['name' => 'Blocked'])->assertRedirect();
    }

    public function test_moving_a_task_reorders_the_destination_bucket(): void
    {
        [$owner] = $this->staffWithEmployee();
        $plan = $this->makePlan($owner);
        [$todo, $doing] = [$plan->buckets[0], $plan->buckets[1]];
        $a = $plan->tasks()->create(['task_bucket_id' => $doing->id, 'title' => 'A', 'position' => 0, 'created_by' => $owner->id]);
        $b = $plan->tasks()->create(['task_bucket_id' => $doing->id, 'title' => 'B', 'position' => 1, 'created_by' => $owner->id]);
        $c = $plan->tasks()->create(['task_bucket_id' => $todo->id, 'title' => 'C', 'position' => 0, 'created_by' => $owner->id]);

        $this->actingAs($owner)->postJson(route('tasks.move', $c), ['task_bucket_id' => $doing->id, 'position' => 1])->assertOk();

        $this->assertSame(['A', 'C', 'B'], $doing->tasks()->pluck('title')->all());
    }

    public function test_cannot_move_a_task_into_another_plans_bucket(): void
    {
        [$owner] = $this->staffWithEmployee();
        $plan = $this->makePlan($owner);
        $other = TaskPlan::create(['name' => 'Other', 'owner_id' => $owner->id]);
        $foreign = $other->buckets()->create(['name' => 'X']);
        $task = $plan->tasks()->create(['task_bucket_id' => $plan->buckets->first()->id, 'title' => 'T', 'created_by' => $owner->id]);

        $this->actingAs($owner)->postJson(route('tasks.move', $task), ['task_bucket_id' => $foreign->id, 'position' => 0])->assertStatus(422);
    }

    public function test_completing_sets_completed_at_and_reopening_clears_it(): void
    {
        [$owner] = $this->staffWithEmployee();
        $plan = $this->makePlan($owner);
        $task = $plan->tasks()->create(['task_bucket_id' => $plan->buckets->first()->id, 'title' => 'T', 'created_by' => $owner->id]);

        $this->actingAs($owner)->post(route('tasks.toggle-complete', $task));
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame('completed', $task->fresh()->progress);

        $this->actingAs($owner)->post(route('tasks.toggle-complete', $task));
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_my_tasks_page_lists_assigned_tasks_and_renders_the_board(): void
    {
        [$owner, $ownerEmployee] = $this->staffWithEmployee();
        $plan = $this->makePlan($owner);
        $task = $plan->tasks()->create(['task_bucket_id' => $plan->buckets->first()->id, 'title' => 'Check bearings', 'due_date' => now()->subDay(), 'created_by' => $owner->id]);
        $task->assignees()->attach($ownerEmployee->id);
        $task->checklistItems()->create(['title' => 'Step 1']);

        $this->actingAs($owner)->get(route('tasks.index'))->assertOk()->assertSee('Check bearings')->assertSee('Overdue');
        $this->actingAs($owner)->get(route('tasks.plans.show', $plan))->assertOk()->assertSee('Check bearings');
        $this->actingAs($owner)->get(route('tasks.plans.show', [$plan, 'filter' => 'mine']))->assertOk()->assertSee('Check bearings');
        $this->actingAs($owner)->get(route('tasks.show', $task))->assertOk()->assertSee('Step 1');
    }
}
