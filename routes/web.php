<?php

use App\Enums\MenuKey;
use App\Enums\PermissionName;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CompetencyGapAnalysisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDevelopmentPlanController;
use App\Http\Controllers\EmployeeOnboardingController;
use App\Http\Controllers\EmployeeSkillAssessmentController;
use App\Http\Controllers\EmployeeSkillChecklistController;
use App\Http\Controllers\GuestSessionController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\JobDescriptionController;
use App\Http\Controllers\LototoBoardSettingsController;
use App\Http\Controllers\LototoBoardsController;
use App\Http\Controllers\LototoController;
use App\Http\Controllers\LototoToolController;
use App\Http\Middleware\AllowLototoQrForm;
use App\Http\Middleware\LogLototoFormAuthorization;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MenuVisibilityController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationChartController;
use App\Http\Controllers\PlantManagerController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\PositionDefaultSkillController;
use App\Http\Controllers\PositionSkillRequirementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCertificateController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShiftCommController;
use App\Http\Controllers\SignatoryController;
use App\Http\Controllers\SkillCatalogController;
use App\Http\Controllers\SkillMatrixController;
use App\Http\Controllers\SpareLockController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskPlanController;
use App\Http\Controllers\TrainingCheckInController;
use App\Http\Controllers\TrainingProgramController;
use App\Http\Controllers\TrainingQuestionController;
use App\Http\Controllers\TrainingQuizController;
use App\Http\Controllers\TrainingRecordController;
use App\Http\Controllers\TrainingSessionController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/guest-login', [GuestSessionController::class, 'start'])->name('guest-login');

// Public, unauthenticated: a new employee completes their own profile via
// the QR/link shown on their profile page - see EmployeeOnboardingController.
Route::get('/onboarding/{token}', [EmployeeOnboardingController::class, 'edit'])->name('onboarding.edit');
Route::post('/onboarding/{token}', [EmployeeOnboardingController::class, 'update'])->name('onboarding.update');

// Public, unauthenticated: the "scan my badge QR code" certificate
// verification pages - anyone with the link can see an employee's verified
// certifications, no login required. See PublicCertificateController.
Route::get('/verify/{employee:employee_number}', [PublicCertificateController::class, 'employee'])->name('verify.employee');
Route::get('/verify/certificates/{certificate}', [PublicCertificateController::class, 'detail'])->name('verify.certificate');
Route::get('/verify/certificates/{certificate}/certificate', [PublicCertificateController::class, 'certificate'])->name('verify.certificate.print');
Route::get('/verify/participants/{participant}', [PublicCertificateController::class, 'participant'])->name('verify.participant');

// A lock's signed QR opens the form without a login; everyone else still needs auth, verified and ViewSafety.
Route::middleware([AllowLototoQrForm::class, LogLototoFormAuthorization::class])->group(function () {
    Route::get('/safety/lototo/form', [LototoController::class, 'form'])
        ->name('safety.lototo.form');
    Route::post('/safety/lototo/form', [LototoController::class, 'storeForm'])
        ->middleware('throttle:20,1')
        ->name('safety.lototo.form.store');
});

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'guest-login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])
    ->get('/organization-chart', [OrganizationChartController::class, 'index'])
    ->name('organization-chart.index');

Route::middleware(['auth', 'verified'])->prefix('overtime')->name('overtime.')->group(function () {
    Route::get('/', [OvertimeController::class, 'index'])->name('index');
    Route::get('/create', [OvertimeController::class, 'create'])->name('create');
    Route::post('/', [OvertimeController::class, 'store'])->name('store');
    Route::get('/export', [OvertimeController::class, 'export'])->name('export');
    Route::post('/mark-submitted', [OvertimeController::class, 'markSubmitted'])->name('mark-submitted');
    Route::get('/{overtimeEntry}/edit', [OvertimeController::class, 'edit'])->name('edit');
    Route::put('/{overtimeEntry}', [OvertimeController::class, 'update'])->name('update');
    Route::post('/{overtimeEntry}/request-edit', [OvertimeController::class, 'requestEdit'])->name('request-edit');
    Route::post('/{overtimeEntry}/approve-edit', [OvertimeController::class, 'approveEdit'])->name('approve-edit');
    Route::post('/{overtimeEntry}/reject-edit', [OvertimeController::class, 'rejectEdit'])->name('reject-edit');
});

Route::middleware(['auth', 'verified'])->prefix('plant-manager')->name('plant-manager.')->group(function () {
    Route::get('/', [PlantManagerController::class, 'edit'])->name('edit');
    Route::put('/', [PlantManagerController::class, 'update'])->middleware('menu.edit:'.MenuKey::Employees->value)->name('update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('employees', EmployeeController::class)
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'menu.edit:'.MenuKey::Employees->value);
    Route::get('/employees-qr-codes', [EmployeeController::class, 'qrCodes'])->name('employees.qr-codes');
    Route::put('/employees-qr-codes/base-url', [EmployeeController::class, 'updateQrBaseUrl'])->name('employees.qr-codes.base-url');
    Route::post('/employees-bulk-destroy', [EmployeeController::class, 'bulkDestroy'])->middleware('menu.edit:'.MenuKey::Employees->value)->name('employees.bulk-destroy');
    Route::post('/employees-import', [EmployeeController::class, 'import'])->middleware('menu.edit:'.MenuKey::Employees->value)->name('employees.import');
    Route::post('/employees/{employee}/onboarding-link', [EmployeeController::class, 'regenerateOnboardingLink'])->middleware('menu.edit:'.MenuKey::Employees->value)->name('employees.onboarding-link');
    Route::post('/employees/{employee}/skill-assessments', [EmployeeSkillAssessmentController::class, 'store'])->middleware('menu.edit:'.MenuKey::SkillsCompetencies->value)->name('employees.skill-assessments.store');
    Route::get('/employees/{employee}/skill-checklist', [EmployeeSkillChecklistController::class, 'edit'])->name('employees.skill-checklist.edit');
    Route::put('/employees/{employee}/skill-checklist', [EmployeeSkillChecklistController::class, 'update'])->middleware('menu.edit:'.MenuKey::SkillsCompetencies->value)->name('employees.skill-checklist.update');
    Route::delete('/employees/{employee}/skill-assessments/{assessment}', [EmployeeSkillAssessmentController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::SkillsCompetencies->value)->name('employees.skill-assessments.destroy');

    Route::post('/employees/{employee}/training-records', [TrainingRecordController::class, 'store'])->middleware('menu.edit:'.MenuKey::Training->value)->name('employees.training-records.store');
    Route::get('/employees/{employee}/training-records/create', [TrainingRecordController::class, 'create'])->middleware('menu.edit:'.MenuKey::Training->value)->name('employees.training-records.create');
    Route::delete('/employees/{employee}/training-records/{record}', [TrainingRecordController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::Training->value)->name('employees.training-records.destroy');

    Route::get('/employees/{employee}/development-plans/create', [EmployeeDevelopmentPlanController::class, 'create'])->middleware('menu.edit:'.MenuKey::DevelopmentPlans->value)->name('employees.development-plans.create');
    Route::post('/employees/{employee}/development-plans', [EmployeeDevelopmentPlanController::class, 'store'])->middleware('menu.edit:'.MenuKey::DevelopmentPlans->value)->name('employees.development-plans.store');
    Route::get('/employees/{employee}/development-plans/{plan}/edit', [EmployeeDevelopmentPlanController::class, 'edit'])->middleware('menu.edit:'.MenuKey::DevelopmentPlans->value)->name('employees.development-plans.edit');
    Route::put('/employees/{employee}/development-plans/{plan}', [EmployeeDevelopmentPlanController::class, 'update'])->middleware('menu.edit:'.MenuKey::DevelopmentPlans->value)->name('employees.development-plans.update');
    Route::delete('/employees/{employee}/development-plans/{plan}', [EmployeeDevelopmentPlanController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::DevelopmentPlans->value)->name('employees.development-plans.destroy');

    Route::get('/employees/{employee}/certificates/create', [CertificateController::class, 'create'])->middleware('menu.edit:'.MenuKey::Certificates->value)->name('employees.certificates.create');
    Route::post('/employees/{employee}/certificates', [CertificateController::class, 'store'])->middleware('menu.edit:'.MenuKey::Certificates->value)->name('employees.certificates.store');
    Route::get('/employees/{employee}/certificates/{certificate}/edit', [CertificateController::class, 'edit'])->middleware('menu.edit:'.MenuKey::Certificates->value)->name('employees.certificates.edit');
    Route::put('/employees/{employee}/certificates/{certificate}', [CertificateController::class, 'update'])->middleware('menu.edit:'.MenuKey::Certificates->value)->name('employees.certificates.update');
    Route::delete('/employees/{employee}/certificates/{certificate}', [CertificateController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::Certificates->value)->name('employees.certificates.destroy');
    Route::get('/certificates/{certificate}/view', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::post('/certificates-import', [CertificateController::class, 'import'])->middleware('menu.edit:'.MenuKey::Certificates->value)->name('certificates.import');
});

Route::pattern('type', implode('|', array_keys(config('master_data'))));

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ManageMasterData->value])
    ->prefix('organization')
    ->name('organization.')
    ->group(function () {
        Route::get('/', [MasterDataController::class, 'landing'])->name('landing');
        Route::get('/{type}', [MasterDataController::class, 'index'])->name('index');
        Route::get('/{type}/export', [MasterDataController::class, 'export'])->name('export');
        Route::post('/{type}/import', [MasterDataController::class, 'import'])->middleware('menu.edit:'.MenuKey::Organization->value)->name('import');
        Route::get('/{type}/create', [MasterDataController::class, 'create'])->middleware('menu.edit:'.MenuKey::Organization->value)->name('create');
        Route::post('/{type}', [MasterDataController::class, 'store'])->middleware('menu.edit:'.MenuKey::Organization->value)->name('store');
        Route::get('/{type}/{id}/edit', [MasterDataController::class, 'edit'])->middleware('menu.edit:'.MenuKey::Organization->value)->name('edit');
        Route::put('/{type}/{id}', [MasterDataController::class, 'update'])->middleware('menu.edit:'.MenuKey::Organization->value)->name('update');
        Route::delete('/{type}/{id}', [MasterDataController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::Organization->value)->name('destroy');
    });

Route::middleware(['auth', 'verified'])->prefix('skills')->name('skills.')->group(function () {
    Route::get('/', function () {
        return view('skills.landing');
    })->name('landing');

    // The master skill list and its position defaults are set by whoever holds
    // ManageSkills through their role (manager/admin). `can:` would also let
    // supervisors in, because their menu-edit override grants skills.manage.
    Route::group(['middleware' => [function ($request, $next) {
        abort_unless($request->user()->hasPermissionTo(PermissionName::ManageSkills->value), 403);

        return $next($request);
    }]], function () {
        Route::prefix('catalog')->name('catalog.')->group(function () {
            Route::get('/', [SkillCatalogController::class, 'index'])->name('index');
            Route::get('/template', [SkillCatalogController::class, 'template'])->name('template');
            Route::get('/export', [SkillCatalogController::class, 'export'])->name('export');
            Route::post('/import', [SkillCatalogController::class, 'import'])->middleware('menu.edit:'.MenuKey::SkillsCompetencies->value)->name('import');
        });

        Route::prefix('position-defaults')->name('position-defaults.')->group(function () {
            Route::get('/', [PositionDefaultSkillController::class, 'index'])->name('index');
            Route::get('/{position}/edit', [PositionDefaultSkillController::class, 'edit'])->name('edit');
            Route::put('/{position}', [PositionDefaultSkillController::class, 'update'])->middleware('menu.edit:'.MenuKey::SkillsCompetencies->value)->name('update');
        });
    });

    Route::middleware('can:'.PermissionName::ManageSkills->value)->prefix('positions')->name('positions.')->group(function () {
        Route::get('/', [PositionSkillRequirementController::class, 'index'])->name('index');
        Route::get('/export', [PositionSkillRequirementController::class, 'export'])->name('export');
        Route::post('/import', [PositionSkillRequirementController::class, 'import'])->middleware('menu.edit:'.MenuKey::SkillsCompetencies->value)->name('import');
        Route::get('/{position}/edit', [PositionSkillRequirementController::class, 'edit'])->name('edit');
        Route::post('/{position}/requirements', [PositionSkillRequirementController::class, 'store'])->middleware('menu.edit:'.MenuKey::SkillsCompetencies->value)->name('requirements.store');
        Route::delete('/{position}/requirements/{requirement}', [PositionSkillRequirementController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::SkillsCompetencies->value)->name('requirements.destroy');
    });
});

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewSkillMatrix->value])
    ->get('/skill-matrix', [SkillMatrixController::class, 'index'])
    ->name('skill-matrix.index');

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewCompetencyGap->value])
    ->get('/competency-gap-analysis', [CompetencyGapAnalysisController::class, 'index'])
    ->name('competency-gap-analysis.index');

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewJobDescriptions->value])
    ->prefix('job-descriptions')
    ->name('job-descriptions.')
    ->group(function () {
        Route::get('/', [JobDescriptionController::class, 'index'])->name('index');
        Route::get('/create', [JobDescriptionController::class, 'create'])->name('create');
        Route::post('/', [JobDescriptionController::class, 'store'])->name('store');
        Route::get('/{jobDescription}', [JobDescriptionController::class, 'show'])->name('show');
        Route::get('/{jobDescription}/edit', [JobDescriptionController::class, 'edit'])->name('edit');
        Route::put('/{jobDescription}', [JobDescriptionController::class, 'update'])->name('update');
        Route::post('/{jobDescription}/new-revision', [JobDescriptionController::class, 'newRevision'])->name('new-revision');
        Route::post('/{jobDescription}/submit-for-review', [JobDescriptionController::class, 'submitForReview'])->name('submit-for-review');
        Route::post('/{jobDescription}/approve', [JobDescriptionController::class, 'approve'])->name('approve');
        Route::post('/{jobDescription}/archive', [JobDescriptionController::class, 'archive'])->name('archive');
    });

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewTraining->value])
    ->prefix('training')
    ->name('training.')
    ->group(function () {
        Route::get('/calendar', [TrainingSessionController::class, 'calendar'])->name('calendar');
        Route::get('/records', [TrainingRecordController::class, 'index'])->name('records.index');
        Route::get('/quiz/{participant}', [TrainingQuizController::class, 'show'])->name('quiz.show');
        Route::post('/quiz/{participant}', [TrainingQuizController::class, 'submit'])->name('quiz.submit');
        Route::post('/records-import', [TrainingRecordController::class, 'import'])->middleware('menu.edit:'.MenuKey::Training->value)->name('records.import');

        // Training Management: program/session administration, including the
        // question bank and participant quiz review (shows correct answers),
        // is restricted to ManageTraining holders only — not every ViewTraining
        // role (e.g. regular staff taking their own quiz) should browse this.
        Route::middleware('can:'.PermissionName::ManageTraining->value)->group(function () {
            Route::get('/programs/{program}/questions', [TrainingQuestionController::class, 'index'])->name('programs.questions.index');
            Route::get('/programs/{program}/questions/export', [TrainingQuestionController::class, 'export'])->name('programs.questions.export');
            Route::post('/programs/{program}/questions/import', [TrainingQuestionController::class, 'import'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.questions.import');
            Route::post('/programs/{program}/questions', [TrainingQuestionController::class, 'store'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.questions.store');
            Route::delete('/programs/{program}/questions/{question}', [TrainingQuestionController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.questions.destroy');

            Route::resource('programs', TrainingProgramController::class)->except(['destroy'])->parameters(['programs' => 'program'])
                ->middlewareFor(['create', 'store', 'edit', 'update'], 'menu.edit:'.MenuKey::Training->value);
            Route::delete('/programs/{program}', [TrainingProgramController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.destroy');

            Route::get('/programs/{program}/sessions/create', [TrainingSessionController::class, 'create'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.sessions.create');
            Route::post('/programs/{program}/sessions', [TrainingSessionController::class, 'store'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.sessions.store');

            Route::get('/sessions/{trainingSession}', [TrainingSessionController::class, 'show'])->name('sessions.show');
            Route::get('/sessions/{trainingSession}/check-in', [TrainingCheckInController::class, 'show'])->name('sessions.check-in');
            Route::post('/sessions/{trainingSession}/check-in', [TrainingCheckInController::class, 'attempt'])->name('sessions.check-in.attempt');
            Route::get('/sessions/{trainingSession}/edit', [TrainingSessionController::class, 'edit'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.edit');
            Route::put('/sessions/{trainingSession}', [TrainingSessionController::class, 'update'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.update');
            Route::delete('/sessions/{trainingSession}', [TrainingSessionController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.destroy');

            Route::post('/sessions/{trainingSession}/participants', [TrainingSessionController::class, 'addParticipant'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.participants.store');
            Route::put('/sessions/{trainingSession}/participants/{participant}', [TrainingSessionController::class, 'updateParticipant'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.participants.update');
            Route::delete('/sessions/{trainingSession}/participants/{participant}', [TrainingSessionController::class, 'removeParticipant'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.participants.destroy');
            Route::get('/sessions/{trainingSession}/participants/{participant}/quiz', [TrainingSessionController::class, 'showParticipantQuiz'])->name('sessions.participants.quiz');
            Route::post('/sessions/{trainingSession}/participants/{participant}/quiz-reset', [TrainingSessionController::class, 'resetParticipantQuiz'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.participants.quiz-reset');
        });
    });

// Task Planner: open to every signed-in employee - access is decided per
// plan by TaskPlanPolicy (owner, assignee, or Administrator), not a role.
Route::middleware(['auth', 'verified'])->prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/', [TaskPlanController::class, 'index'])->name('index');
    Route::post('/plans', [TaskPlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}', [TaskPlanController::class, 'show'])->name('plans.show');
    Route::put('/plans/{plan}', [TaskPlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [TaskPlanController::class, 'destroy'])->name('plans.destroy');
    Route::post('/plans/{plan}/buckets', [TaskPlanController::class, 'storeBucket'])->name('plans.buckets.store');
    Route::put('/plans/{plan}/buckets/{bucket}', [TaskPlanController::class, 'updateBucket'])->name('plans.buckets.update');
    Route::delete('/plans/{plan}/buckets/{bucket}', [TaskPlanController::class, 'destroyBucket'])->name('plans.buckets.destroy');
    Route::post('/plans/{plan}/tasks', [TaskController::class, 'store'])->name('store');
    Route::get('/{task}', [TaskController::class, 'show'])->name('show');
    Route::put('/{task}', [TaskController::class, 'update'])->name('update');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
    Route::post('/{task}/toggle-complete', [TaskController::class, 'toggleComplete'])->name('toggle-complete');
    Route::post('/{task}/move', [TaskController::class, 'move'])->name('move');
    Route::post('/{task}/checklist', [TaskController::class, 'storeChecklistItem'])->name('checklist.store');
    Route::post('/{task}/checklist/{item}/toggle', [TaskController::class, 'toggleChecklistItem'])->name('checklist.toggle');
    Route::delete('/{task}/checklist/{item}', [TaskController::class, 'destroyChecklistItem'])->name('checklist.destroy');
});

// Shift Comm: technicians' machine activity log shared across shifts. Open to
// every signed-in non-guest user; edit/delete limited by ShiftCommPolicy.
Route::middleware(['auth', 'verified'])->prefix('shift-comm')->name('shift-comm.')->group(function () {
    Route::get('/', [ShiftCommController::class, 'index'])->name('index');
    Route::get('/create', [ShiftCommController::class, 'create'])->name('create');
    Route::get('/machine-lookup', [ShiftCommController::class, 'lookupMachine'])->name('machine-lookup');
    Route::post('/', [ShiftCommController::class, 'store'])->name('store');
    Route::get('/{shiftComm}', [ShiftCommController::class, 'show'])->name('show');
    Route::get('/{shiftComm}/edit', [ShiftCommController::class, 'edit'])->name('edit');
    Route::put('/{shiftComm}', [ShiftCommController::class, 'update'])->name('update');
    Route::delete('/{shiftComm}', [ShiftCommController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewDevelopmentPlans->value])
    ->get('/development-plans', [EmployeeDevelopmentPlanController::class, 'index'])
    ->name('development-plans.index');

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewCertificates->value])
    ->get('/certificates', [CertificateController::class, 'index'])
    ->name('certificates.index');

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewCertificates->value])
    ->get('/certificates/recertification', [CertificateController::class, 'recertification'])
    ->name('certificates.recertification');

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewCertificates->value])
    ->resource('signatories', SignatoryController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ManageUsers->value])
    ->get('/audit-logs', [AuditLogController::class, 'index'])
    ->name('audit-logs.index');

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ManageUsers->value])
    ->prefix('admin/users')
    ->name('admin.users.')
    ->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
    });

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ManageUsers->value])
    ->prefix('admin/menu-visibility')
    ->name('admin.menu-visibility.')
    ->group(function () {
        Route::get('/', [MenuVisibilityController::class, 'index'])->name('index');
        Route::post('/bulk', [MenuVisibilityController::class, 'bulk'])->name('bulk');
        Route::get('/{user}', [MenuVisibilityController::class, 'edit'])->name('edit');
        Route::put('/{user}', [MenuVisibilityController::class, 'update'])->name('update');
    });

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ManageUsers->value])
    ->prefix('admin/roles')
    ->name('admin.roles.')
    ->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/reset-defaults', [RoleController::class, 'resetDefaults'])->name('reset-defaults');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
    });

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ManageSettings->value])
    ->group(function () {
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    });

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewReports->value])
    ->prefix('reports')
    ->name('reports.')
    ->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/training-hours', [ReportController::class, 'trainingHours'])->name('training-hours');
        Route::get('/development-summary', [ReportController::class, 'developmentSummary'])->name('development-summary');
        Route::get('/assessment-history', [ReportController::class, 'assessmentHistory'])->name('assessment-history');
        Route::post('/assessment-history-import', [ReportController::class, 'assessmentHistoryImport'])->name('assessment-history.import');
        Route::get('/combined-export', [ReportController::class, 'combinedExport'])->name('combined-export');
    });

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewSafety->value])
    ->prefix('safety')
    ->name('safety.')
    ->group(function () {
        Route::get('/lototo', [LototoController::class, 'index'])
            ->name('lototo.index');
        Route::get('/lototo/boards/{board}', [LototoController::class, 'board'])
            ->name('lototo.boards.show');

        Route::middleware('can:'.PermissionName::ManageSafety->value)->group(function () {
            Route::get('/lototo/boards', [LototoBoardsController::class, 'index'])
                ->name('lototo.boards.index');
            Route::post('/lototo/boards', [LototoBoardsController::class, 'store'])
                ->name('lototo.boards.store');
            Route::patch('/lototo/boards/{board}/status', [LototoBoardsController::class, 'updateStatus'])
                ->name('lototo.boards.status');
            Route::delete('/lototo/boards/{board}', [LototoBoardsController::class, 'destroy'])
                ->name('lototo.boards.destroy');
            Route::get('/lototo/boards/{board}/layout', [LototoBoardSettingsController::class, 'layout'])
                ->name('lototo.boards.layout');
            Route::put('/lototo/boards/{board}/layout', [LototoBoardSettingsController::class, 'updateLayout'])
                ->name('lototo.boards.layout.update');
            // Kelola Tools / Kelola Spare Lock popups of Edit Mode (JSON). The picture is sent with POST (multipart).
            Route::post('/lototo/boards/{board}/tools', [LototoToolController::class, 'store'])
                ->name('lototo.boards.tools.store');
            Route::post('/lototo/boards/{board}/tools/{tool}', [LototoToolController::class, 'update'])
                ->name('lototo.boards.tools.update');
            Route::delete('/lototo/boards/{board}/tools/{tool}', [LototoToolController::class, 'destroy'])
                ->name('lototo.boards.tools.destroy');
            Route::post('/lototo/boards/{board}/spare-locks', [SpareLockController::class, 'store'])
                ->name('lototo.boards.spare-locks.store');
            Route::delete('/lototo/boards/{board}/spare-locks/{spareLock}', [SpareLockController::class, 'destroy'])
                ->name('lototo.boards.spare-locks.destroy');
        });

        // QR Code page and its print photos: Safety managers and the read-only Guest viewer (checked in the controller).
        Route::get('/lototo/print/employees/{employee}/photo', [LototoBoardSettingsController::class, 'printPhoto'])
            ->name('lototo.print.photo');
        Route::get('/lototo/qr-codes', [LototoBoardSettingsController::class, 'qrCodes'])
            ->name('lototo.qr-codes');

        Route::get('/lototo/spare-lock/{spareLock}', [SpareLockController::class, 'show'])
            ->name('lototo.spare-locks.show');

        Route::get('/lototo/follow-up/{activity}', [LototoController::class, 'followUp'])
            ->name('lototo.follow-up');
        Route::post('/lototo/follow-up/{activity}', [LototoController::class, 'submitFollowUp'])
            ->name('lototo.follow-up.store');

        Route::get('/lototo/tools/{tool}/form', [LototoToolController::class, 'form'])
            ->name('lototo.tools.form');
        Route::post('/lototo/tools/{tool}/form', [LototoToolController::class, 'storeForm'])
            ->middleware('throttle:20,1')
            ->name('lototo.tools.form.store');
        Route::get('/lototo/tools/{tool}/image', [LototoToolController::class, 'image'])
            ->name('lototo.tools.image');

        Route::get('/lototo/{employee}', [LototoController::class, 'show'])
            ->name('lototo.show');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ManageNews->value])
    ->prefix('news')
    ->name('news.')
    ->group(function () {
        Route::get('/', [NewsController::class, 'index'])->name('index');
        Route::get('/create', [NewsController::class, 'create'])->name('create');
        Route::post('/', [NewsController::class, 'store'])->name('store');
        Route::get('/{news}/edit', [NewsController::class, 'edit'])->name('edit');
        Route::put('/{news}', [NewsController::class, 'update'])->name('update');
        Route::delete('/{news}', [NewsController::class, 'destroy'])->name('destroy');
    });

// News detail is viewable by anyone logged in (not just news managers) -
// it's what the dashboard carousel links to when a user clicks a headline.
Route::middleware(['auth', 'verified'])
    ->get('/news/{news}', [NewsController::class, 'show'])
    ->name('news.show');

Route::middleware(['auth', 'verified'])
    ->post('/news/{news}/acknowledge', [NewsController::class, 'acknowledge'])
    ->name('news.acknowledge');

Route::middleware(['auth', 'verified'])->get('/help', [HelpController::class, 'index'])->name('help.index');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
