<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_employees(): void
    {
        $response = $this->get(route('employees.index'));

        $response->assertRedirect('/guest-login');
    }

    public function test_user_without_any_employee_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('employees.index'));

        $response->assertForbidden();
    }

    public function test_administrator_sees_all_employees(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        Employee::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 3);
    }

    public function test_index_defaults_to_the_gallery_with_every_match_and_offers_the_table(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        Employee::factory()->count(20)->create();

        // Gallery (default): every match as a photo card, not 15 per page; no bulk delete.
        $this->actingAs($admin)->get(route('employees.index', ['applied' => '1']))
            ->assertOk()
            ->assertViewHas('view', 'gallery')
            ->assertViewHas('galleryEmployees', fn ($employees) => $employees->count() === 20)
            ->assertSee('id="employee-gallery"', false)
            ->assertDontSee('id="bulk-delete-form"', false);

        // Table: the existing paginated list with bulk delete; pager links keep the view.
        $this->actingAs($admin)->get(route('employees.index', ['applied' => '1', 'view' => 'table']))
            ->assertOk()
            ->assertViewHas('view', 'table')
            ->assertViewHas('employees', fn ($employees) => $employees->count() === 15 && str_contains($employees->nextPageUrl(), 'view=table'))
            ->assertSee('id="bulk-delete-form"', false)
            ->assertDontSee('id="employee-gallery"', false);
    }

    public function test_gallery_respects_filters_and_visibility(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        Employee::factory()->create(['full_name' => 'Gallery Match Person']);
        Employee::factory()->create(['full_name' => 'Somebody Else Entirely']);

        $this->actingAs($admin)->get(route('employees.index', ['applied' => '1', 'search' => 'Gallery Match']))
            ->assertOk()
            ->assertViewHas('galleryEmployees', fn ($employees) => $employees->pluck('full_name')->all() === ['Gallery Match Person'])
            ->assertSee('Gallery Match Person')
            ->assertDontSee('Somebody Else Entirely');
    }

    public function test_profile_shows_qr_codes_in_overlays_and_reopens_onboarding_after_generating(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $employee = Employee::factory()->create();

        $this->actingAs($admin)->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee("\$dispatch('open-modal', 'certificate-qr')", false)
            ->assertSee("\$dispatch('open-modal', 'onboarding-qr')", false)
            ->assertSee(route('verify.employee', $employee), false);

        $this->actingAs($admin)->post(route('employees.onboarding-link', $employee))
            ->assertRedirect(route('employees.show', $employee))
            ->assertSessionHas('open_modal', 'onboarding-qr');

        $this->actingAs($admin)->get(route('employees.show', $employee))
            ->assertSee(route('onboarding.edit', $employee->fresh()->profile_completion_token), false);
    }

    public function test_people_development_sees_all_employees(): void
    {
        // People Development (HR) is an intentional exception to hierarchy
        // scoping - it's a cross-organization role, not a line-management
        // one, so it keeps ViewAllEmployees like Administrator.
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        Employee::factory()->count(3)->create();

        $response = $this->actingAs($hr)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 3);
    }

    public function test_manager_sees_only_themselves_and_their_direct_reports(): void
    {
        // Maintenance Manager is scoped by hierarchy like Supervisor - it's
        // a line-management role, not a cross-organization one like People
        // Development.
        $managerUser = User::factory()->create();
        $managerUser->assignRole(RoleName::MaintenanceManager->value);
        $managerEmployee = Employee::factory()->create(['user_id' => $managerUser->id]);

        $directReport = Employee::factory()->create(['supervisor_id' => $managerEmployee->id]);
        Employee::factory()->create();

        $response = $this->actingAs($managerUser)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', function ($employees) use ($managerEmployee, $directReport) {
            $ids = $employees->pluck('id')->all();

            return $employees->total() === 2
                && in_array($managerEmployee->id, $ids)
                && in_array($directReport->id, $ids);
        });
    }

    public function test_maintenance_staff_can_only_see_their_own_employee_record(): void
    {
        $staffUser = User::factory()->create();
        $staffUser->assignRole(RoleName::MaintenanceStaff->value);

        $ownEmployee = Employee::factory()->create(['user_id' => $staffUser->id]);
        Employee::factory()->count(2)->create();

        $response = $this->actingAs($staffUser)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 1
            && $employees->first()->id === $ownEmployee->id);
    }

    public function test_maintenance_staff_cannot_view_another_employees_profile(): void
    {
        $staffUser = User::factory()->create();
        $staffUser->assignRole(RoleName::MaintenanceStaff->value);
        Employee::factory()->create(['user_id' => $staffUser->id]);

        $otherEmployee = Employee::factory()->create();

        $response = $this->actingAs($staffUser)->get(route('employees.show', $otherEmployee));

        $response->assertForbidden();
    }

    public function test_supervisor_sees_only_themselves_and_their_direct_reports(): void
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisorEmployee = Employee::factory()->create(['user_id' => $supervisorUser->id]);

        $directReport = Employee::factory()->create(['supervisor_id' => $supervisorEmployee->id]);
        // A report-of-a-report: not visible, since visibility is one level
        // deep only (direct reports), not the whole chain beneath them.
        Employee::factory()->create(['supervisor_id' => $directReport->id]);
        // Someone else's employee entirely: never visible.
        Employee::factory()->create();

        $response = $this->actingAs($supervisorUser)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', function ($employees) use ($supervisorEmployee, $directReport) {
            $ids = $employees->pluck('id')->all();

            return $employees->total() === 2
                && in_array($supervisorEmployee->id, $ids)
                && in_array($directReport->id, $ids);
        });
    }

    public function test_supervisor_with_no_direct_reports_sees_only_themselves(): void
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisorEmployee = Employee::factory()->create(['user_id' => $supervisorUser->id]);

        Employee::factory()->count(2)->create();

        $response = $this->actingAs($supervisorUser)->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 1
            && $employees->first()->id === $supervisorEmployee->id);
    }

    public function test_maintenance_staff_cannot_create_employees(): void
    {
        $staffUser = User::factory()->create();
        $staffUser->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($staffUser)->get(route('employees.create'));

        $response->assertForbidden();
    }

    public function test_administrator_can_create_an_employee(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-99999',
            'full_name' => 'Test Employee',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-99999']);
    }

    public function test_qr_codes_page_lists_visible_employees_with_their_verification_link(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $employee = Employee::factory()->create(['employee_number' => 'EMP-QR-1', 'full_name' => 'QR Test Employee']);

        $response = $this->actingAs($admin)->get(route('employees.qr-codes'));

        $response->assertOk();
        $response->assertSee('QR Test Employee');
        // The full URL is composed client-side from this relative path plus
        // the site-wide (Administrator-only) base URL - see resources/js/qr-base-url.js.
        $response->assertSee(route('verify.employee', $employee, false), false);
    }

    public function test_administrator_can_change_the_qr_base_url(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $this->actingAs($admin)
            ->put(route('employees.qr-codes.base-url'), ['qr_base_url' => 'https://mpd.example.com/'])
            ->assertRedirect();

        $this->assertSame('https://mpd.example.com', \App\Models\Setting::get('qr_base_url'));
        $this->actingAs($admin)->get(route('employees.qr-codes'))
            ->assertSee('data-base-url="https://mpd.example.com"', false);
    }

    public function test_non_administrators_cannot_change_the_qr_base_url(): void
    {
        foreach ([RoleName::MaintenanceManager, RoleName::PeopleDevelopment, RoleName::MaintenanceStaff, RoleName::Guest] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);

            $this->actingAs($user)
                ->put(route('employees.qr-codes.base-url'), ['qr_base_url' => 'https://evil.example.com'])
                ->assertForbidden();
        }

        $this->assertSame('', \App\Models\Setting::get('qr_base_url'));
    }

    public function test_qr_base_url_must_be_a_valid_http_url(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $this->actingAs($admin)
            ->put(route('employees.qr-codes.base-url'), ['qr_base_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('qr_base_url');
    }

    public function test_maintenance_staff_only_sees_their_own_qr_code(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $own = Employee::factory()->create(['user_id' => $staff->id, 'full_name' => 'Self Employee']);
        $other = Employee::factory()->create(['full_name' => 'Other Employee']);

        $response = $this->actingAs($staff)->get(route('employees.qr-codes'));

        $response->assertOk();
        $response->assertSee('Self Employee');
        $response->assertDontSee('Other Employee');
    }

    public function test_guests_cannot_view_the_qr_codes_page(): void
    {
        $this->get(route('employees.qr-codes'))->assertRedirect('/guest-login');
    }

    public function test_administrator_can_set_an_employees_license_number(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-LIC-1',
            'full_name' => 'Licensed Employee',
            'license_number' => 'SIO-12345',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-LIC-1', 'license_number' => 'SIO-12345']);
    }

    public function test_administrator_can_create_an_employee_with_the_new_classification_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $businessUnit = \App\Models\BusinessUnit::factory()->create();
        $skillPosition = \App\Models\SkillPosition::factory()->create();
        $employmentSource = \App\Models\EmploymentSource::factory()->create();

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-88888',
            'full_name' => 'Classified Employee',
            'business_unit_id' => $businessUnit->id,
            'skill_position_id' => $skillPosition->id,
            'employment_source_id' => $employmentSource->id,
            'workforce_category' => 'BC',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-88888',
            'business_unit_id' => $businessUnit->id,
            'skill_position_id' => $skillPosition->id,
            'employment_source_id' => $employmentSource->id,
            'workforce_category' => 'BC',
        ]);
    }

    public function test_creating_an_employee_rejects_an_invalid_workforce_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-77777',
            'full_name' => 'Bad Category Employee',
            'workforce_category' => 'NOT-A-REAL-CATEGORY',
        ]);

        $response->assertSessionHasErrors('workforce_category');
        $this->assertDatabaseMissing('employees', ['employee_number' => 'EMP-77777']);
    }

    public function test_administrator_can_upload_a_photo_when_creating_an_employee(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => 'EMP-66666',
            'full_name' => 'Photo Employee',
            'photo' => \Illuminate\Http\UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'),
        ]);

        $response->assertRedirect();
        $employee = Employee::where('employee_number', 'EMP-66666')->firstOrFail();
        $this->assertNotNull($employee->photo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($employee->photo_path);
    }

    public function test_administrator_can_delete_an_employee(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $employee = Employee::factory()->create();

        $response = $this->actingAs($admin)->delete(route('employees.destroy', $employee));

        $response->assertRedirect(route('employees.index'));
        $this->assertSoftDeleted($employee);
    }

    public function test_index_can_be_filtered_by_business_unit_employment_type_source_status_supervisor_and_shift(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $businessUnit = \App\Models\BusinessUnit::factory()->create();
        $employmentType = \App\Models\EmploymentType::factory()->create();
        $employmentSource = \App\Models\EmploymentSource::factory()->create();
        $employmentStatus = \App\Models\EmploymentStatus::factory()->create();
        $shift = \App\Models\Shift::factory()->create();
        $supervisor = Employee::factory()->create();

        $matching = Employee::factory()->create([
            'business_unit_id' => $businessUnit->id,
            'employment_type_id' => $employmentType->id,
            'employment_source_id' => $employmentSource->id,
            'employment_status_id' => $employmentStatus->id,
            'shift_id' => $shift->id,
            'supervisor_id' => $supervisor->id,
        ]);
        Employee::factory()->create();

        foreach ([
            'business_unit_id' => $businessUnit->id,
            'employment_type_id' => $employmentType->id,
            'employment_source_id' => $employmentSource->id,
            'employment_status_id' => $employmentStatus->id,
            'shift_id' => $shift->id,
            'supervisor_id' => $supervisor->id,
        ] as $field => $value) {
            $response = $this->actingAs($admin)->get(route('employees.index', [$field => $value, 'applied' => 1]));

            $response->assertOk();
            $response->assertViewHas('employees', fn ($employees) => $employees->total() === 1
                && $employees->first()->id === $matching->id);
        }
    }

    public function test_search_matches_employee_number_and_name_but_not_email(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $byNumber = Employee::factory()->create(['employee_number' => 'EMP-55555', 'full_name' => 'Zzz Unrelated']);
        $byName = Employee::factory()->create(['employee_number' => 'EMP-11111', 'full_name' => 'Findable Person']);
        Employee::factory()->create(['employee_number' => 'EMP-22222', 'full_name' => 'Zzz Other', 'email' => 'findable@example.com']);

        $byNumberResponse = $this->actingAs($admin)->get(route('employees.index', ['search' => 'EMP-55555']));
        $byNumberResponse->assertViewHas('employees', fn ($employees) => $employees->total() === 1 && $employees->first()->id === $byNumber->id);

        $byNameResponse = $this->actingAs($admin)->get(route('employees.index', ['search' => 'Findable']));
        $byNameResponse->assertViewHas('employees', fn ($employees) => $employees->total() === 1 && $employees->first()->id === $byName->id);

        $byEmailResponse = $this->actingAs($admin)->get(route('employees.index', ['search' => 'findable@example.com']));
        $byEmailResponse->assertViewHas('employees', fn ($employees) => $employees->total() === 0);
    }

    public function test_the_status_filter_defaults_to_active_when_not_specified(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $activeStatus = \App\Models\EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $resignedStatus = \App\Models\EmploymentStatus::factory()->create(['code' => 'RESIGNED']);

        $activeEmployee = Employee::factory()->create(['employment_status_id' => $activeStatus->id]);
        Employee::factory()->create(['employment_status_id' => $resignedStatus->id]);

        // No employment_status_id in the URL at all -> defaults to Active only.
        $response = $this->actingAs($admin)->get(route('employees.index'));
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 1
            && $employees->first()->id === $activeEmployee->id);
        $response->assertViewHas('filters', fn ($filters) => $filters['employment_status_id'] === [$activeStatus->id]);

        // Explicitly requesting "All Statuses" (nothing ticked, but the form's
        // hidden "applied" field is present) is respected.
        $allResponse = $this->actingAs($admin)->get(route('employees.index', ['applied' => '1']));
        $allResponse->assertViewHas('employees', fn ($employees) => $employees->total() === 2);
    }

    public function test_index_filters_accept_multiple_values(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $unitA = \App\Models\BusinessUnit::factory()->create();
        $unitB = \App\Models\BusinessUnit::factory()->create();
        $unitC = \App\Models\BusinessUnit::factory()->create();

        $matchA = Employee::factory()->create(['business_unit_id' => $unitA->id]);
        $matchB = Employee::factory()->create(['business_unit_id' => $unitB->id]);
        Employee::factory()->create(['business_unit_id' => $unitC->id]);

        $response = $this->actingAs($admin)->get(route('employees.index', [
            'business_unit_id' => [$unitA->id, $unitB->id],
            'applied' => 1,
        ]));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === 2
            && $employees->pluck('id')->sort()->values()->all() === collect([$matchA->id, $matchB->id])->sort()->values()->all());
    }

    public function test_a_manager_can_bulk_delete_selected_employees(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $first = Employee::factory()->create();
        $second = Employee::factory()->create();
        $untouched = Employee::factory()->create();

        $response = $this->actingAs($admin)->post(route('employees.bulk-destroy'), [
            'employee_ids' => [$first->id, $second->id],
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertDatabaseHas('employees', ['id' => $untouched->id, 'deleted_at' => null]);
    }

    public function test_bulk_delete_is_forbidden_for_a_user_without_manage_employees(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $employee = Employee::factory()->create();

        $response = $this->actingAs($staff)->post(route('employees.bulk-destroy'), [
            'employee_ids' => [$employee->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'deleted_at' => null]);
    }

    public function test_bulk_delete_only_removes_employees_visible_to_the_acting_user(): void
    {
        // Manager is both hierarchy-scoped (self + direct reports only) and
        // holds ManageEmployees by default, so it can actually reach this
        // action - Supervisor doesn't hold ManageEmployees at all.
        $managerUser = User::factory()->create();
        $managerUser->assignRole(RoleName::MaintenanceManager->value);
        $managerEmployee = Employee::factory()->create(['user_id' => $managerUser->id]);
        $directReport = Employee::factory()->create(['supervisor_id' => $managerEmployee->id]);
        $outsideScope = Employee::factory()->create();

        $response = $this->actingAs($managerUser)->post(route('employees.bulk-destroy'), [
            'employee_ids' => [$directReport->id, $outsideScope->id],
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertSoftDeleted($directReport);
        $this->assertDatabaseHas('employees', ['id' => $outsideScope->id, 'deleted_at' => null]);
    }

    /**
     * Builds a real .xlsx file (not a fake/dummy one) since the import
     * endpoint actually parses it with PhpSpreadsheet - wraps it as an
     * UploadedFile the same way a browser upload would arrive.
     */
    private function makeImportFile(array $header, array $rows): \Illuminate\Http\UploadedFile
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A'.$rowNumber);
            $rowNumber++;
        }

        $path = tempnam(sys_get_temp_dir(), 'import-test-').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        return new \Illuminate\Http\UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_an_administrator_can_bulk_update_employees_via_xlsx_import(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $businessUnit = \App\Models\BusinessUnit::factory()->create(['name' => 'Tire Cord']);
        $employee = Employee::factory()->create(['employee_number' => 'EMP-30001', 'workforce_category' => null]);

        $file = $this->makeImportFile(
            [
                'Employee Number', 'Full Name', 'Business Unit', 'Department', 'Maintenance Team',
                'Position', 'Skill Position', 'Employment Type', 'Employment Source', 'Management',
                'Employment Status', 'Shift', 'Supervisor (Employee Number)',
            ],
            [
                ['EMP-30001', '', 'Tire Cord', '', '', '', '', '', '', 'BC', '', '', ''],
            ]
        );

        $response = $this->actingAs($admin)->post(route('employees.import'), ['file' => $file]);

        $response->assertRedirect(route('employees.index'));
        $employee->refresh();
        $this->assertSame($businessUnit->id, $employee->business_unit_id);
        $this->assertSame('BC', $employee->workforce_category);
    }

    public function test_import_only_applies_the_selected_columns(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $employee = Employee::factory()->create(['employee_number' => 'EMP-30010', 'full_name' => 'Original Name', 'sap_id' => 'OLD-SAP']);

        $file = $this->makeImportFile(
            ['Employee Number', 'Full Name', 'ID SAP'],
            [['EMP-30010', 'Changed Name', 'NEW-SAP']]
        );

        $this->actingAs($admin)->post(route('employees.import'), ['file' => $file, 'columns' => ['ID SAP']])
            ->assertRedirect(route('employees.index'));

        $employee->refresh();
        $this->assertSame('NEW-SAP', $employee->sap_id);
        $this->assertSame('Original Name', $employee->full_name);
    }

    public function test_import_cannot_create_an_employee_when_full_name_is_not_selected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $file = $this->makeImportFile(
            ['Employee Number', 'Full Name', 'ID SAP'],
            [['EMP-30011', 'Someone New', 'SAP-1']]
        );

        $response = $this->actingAs($admin)->post(route('employees.import'), ['file' => $file, 'columns' => ['ID SAP']]);

        $response->assertSessionHas('import_errors');
        $this->assertDatabaseMissing('employees', ['employee_number' => 'EMP-30011']);
    }

    public function test_import_rejects_an_unknown_column_selection(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $file = $this->makeImportFile(['Employee Number'], [['EMP-30012']]);

        $this->actingAs($admin)->post(route('employees.import'), ['file' => $file, 'columns' => ['password']])
            ->assertSessionHasErrors('columns.0');
    }

    public function test_import_updates_profile_columns_including_technical_background(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $employee = Employee::factory()->create(['employee_number' => 'EMP-30020']);

        $file = $this->makeImportFile(
            ['Employee Number', 'Technical Background', 'Gender', 'Date of Birth', 'Date Joined', 'Email', 'Phone', 'Years of Experience', 'Notes', 'License Number', 'Education', 'Preferred Name'],
            [['EMP-30020', 'Mechanical, Electrical', 'female', '1990-05-17', '31/01/2020', 'a.b@example.com', '0812', 7, 'Some note', 'LIC-1', 'S1 Teknik', 'Abi']]
        );

        $this->actingAs($admin)->post(route('employees.import'), ['file' => $file])->assertRedirect(route('employees.index'));

        $employee->refresh();
        $this->assertSame('Mechanical, Electrical', $employee->technical_background);
        $this->assertSame('Female', $employee->gender);
        $this->assertSame('1990-05-17', $employee->date_of_birth->format('Y-m-d'));
        $this->assertSame('2020-01-31', $employee->date_joined->format('Y-m-d'));
        $this->assertSame('a.b@example.com', $employee->email);
        $this->assertSame(7, $employee->years_of_experience);
        $this->assertSame('LIC-1', $employee->license_number);
        $this->assertSame('S1 Teknik', $employee->education);
        $this->assertSame('Abi', $employee->preferred_name);
        $this->assertSame('Some note', $employee->notes);
    }

    public function test_import_rejects_invalid_profile_values_without_touching_the_employee(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        Employee::factory()->create(['email' => 'taken@example.com']);
        $employee = Employee::factory()->create(['employee_number' => 'EMP-30021', 'gender' => null, 'email' => null, 'date_of_birth' => null]);

        $file = $this->makeImportFile(
            ['Employee Number', 'Gender', 'Date of Birth', 'Email'],
            [['EMP-30021', 'Other', '31/02/2020', 'taken@example.com']]
        );

        $response = $this->actingAs($admin)->post(route('employees.import'), ['file' => $file]);

        $response->assertSessionHas('import_errors');
        $employee->refresh();
        $this->assertNull($employee->gender);
        $this->assertNull($employee->date_of_birth);
        $this->assertNull($employee->email);
    }

    public function test_export_includes_every_importable_column(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        Employee::factory()->create(['technical_background' => 'Hydraulics']);

        $response = $this->actingAs($admin)->get(route('employees.index', ['export' => 'xlsx', 'employment_status_id' => '']));

        $response->assertOk();
    }

    public function test_import_reports_an_unmatched_lookup_value_without_crashing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $employee = Employee::factory()->create(['employee_number' => 'EMP-30002']);
        $originalBusinessUnitId = $employee->business_unit_id;

        $file = $this->makeImportFile(
            ['Employee Number', 'Full Name', 'Business Unit', 'Department', 'Maintenance Team', 'Position', 'Skill Position', 'Employment Type', 'Employment Source', 'Management', 'Employment Status', 'Shift', 'Supervisor (Employee Number)'],
            [['EMP-30002', '', 'Nonexistent Unit', '', '', '', '', '', '', '', '', '', '']]
        );

        $response = $this->actingAs($admin)->post(route('employees.import'), ['file' => $file]);

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('import_errors');
        $this->assertSame($originalBusinessUnitId, $employee->fresh()->business_unit_id);
    }

    public function test_import_skips_a_row_for_an_employee_number_that_does_not_exist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $file = $this->makeImportFile(
            ['Employee Number', 'Full Name', 'Business Unit', 'Department', 'Maintenance Team', 'Position', 'Skill Position', 'Employment Type', 'Employment Source', 'Management', 'Employment Status', 'Shift', 'Supervisor (Employee Number)'],
            [['EMP-NOPE', '', '', '', '', '', '', '', '', '', '', '', '']]
        );

        $response = $this->actingAs($admin)->post(route('employees.import'), ['file' => $file]);

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('import_errors');
    }

    public function test_import_is_forbidden_for_a_user_without_manage_employees(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        Employee::factory()->create(['employee_number' => 'EMP-30003']);

        $file = $this->makeImportFile(
            ['Employee Number'],
            [['EMP-30003']]
        );

        $response = $this->actingAs($staff)->post(route('employees.import'), ['file' => $file]);

        $response->assertForbidden();
    }

    public function test_import_only_updates_employees_visible_to_the_importer(): void
    {
        $managerUser = User::factory()->create();
        $managerUser->assignRole(RoleName::MaintenanceManager->value);
        $managerEmployee = Employee::factory()->create(['user_id' => $managerUser->id]);
        $directReport = Employee::factory()->create(['supervisor_id' => $managerEmployee->id, 'employee_number' => 'EMP-30004']);
        $outsideScope = Employee::factory()->create(['employee_number' => 'EMP-30005']);
        $outsideScopeOriginalBusinessUnitId = $outsideScope->business_unit_id;

        $businessUnit = \App\Models\BusinessUnit::factory()->create(['name' => 'Dramix']);

        $file = $this->makeImportFile(
            ['Employee Number', 'Full Name', 'Business Unit', 'Department', 'Maintenance Team', 'Position', 'Skill Position', 'Employment Type', 'Employment Source', 'Management', 'Employment Status', 'Shift', 'Supervisor (Employee Number)'],
            [
                ['EMP-30004', '', 'Dramix', '', '', '', '', '', '', '', '', '', ''],
                ['EMP-30005', '', 'Dramix', '', '', '', '', '', '', '', '', '', ''],
            ]
        );

        $response = $this->actingAs($managerUser)->post(route('employees.import'), ['file' => $file]);

        $response->assertRedirect(route('employees.index'));
        $this->assertSame($businessUnit->id, $directReport->fresh()->business_unit_id);
        $this->assertSame($outsideScopeOriginalBusinessUnitId, $outsideScope->fresh()->business_unit_id);
    }
}
