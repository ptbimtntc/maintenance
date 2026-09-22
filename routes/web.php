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
use App\Http\Controllers\GuestSessionController;
use App\Http\Controllers\JobDescriptionController;
use App\Http\Controllers\LototoController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationChartController;
use App\Http\Controllers\PositionSkillRequirementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCertificateController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SignatoryController;
use App\Http\Controllers\SkillMatrixController;
use App\Http\Controllers\TrainingProgramController;
use App\Http\Controllers\TrainingRecordController;
use App\Http\Controllers\TrainingSessionController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::post('/guest-login', [GuestSessionController::class, 'start'])->name('guest-login');

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

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])
    ->get('/organization-chart', [OrganizationChartController::class, 'index'])
    ->name('organization-chart.index');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('employees', EmployeeController::class)
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'menu.edit:'.MenuKey::Employees->value);
    Route::post('/employees-bulk-destroy', [EmployeeController::class, 'bulkDestroy'])->middleware('menu.edit:'.MenuKey::Employees->value)->name('employees.bulk-destroy');
    Route::post('/employees-import', [EmployeeController::class, 'import'])->middleware('menu.edit:'.MenuKey::Employees->value)->name('employees.import');
    Route::post('/employees/{employee}/onboarding-link', [EmployeeController::class, 'regenerateOnboardingLink'])->middleware('menu.edit:'.MenuKey::Employees->value)->name('employees.onboarding-link');
    Route::post('/employees/{employee}/skill-assessments', [EmployeeSkillAssessmentController::class, 'store'])->middleware('menu.edit:'.MenuKey::SkillsCompetencies->value)->name('employees.skill-assessments.store');
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
        Route::get('/quiz/{participant}', [\App\Http\Controllers\TrainingQuizController::class, 'show'])->name('quiz.show');
        Route::post('/quiz/{participant}', [\App\Http\Controllers\TrainingQuizController::class, 'submit'])->name('quiz.submit');
        Route::post('/records-import', [TrainingRecordController::class, 'import'])->middleware('menu.edit:'.MenuKey::Training->value)->name('records.import');

        // Training Management: program/session administration, including the
        // question bank and participant quiz review (shows correct answers),
        // is restricted to ManageTraining holders only — not every ViewTraining
        // role (e.g. regular staff taking their own quiz) should browse this.
        Route::middleware('can:'.PermissionName::ManageTraining->value)->group(function () {
            Route::get('/programs/{program}/questions', [\App\Http\Controllers\TrainingQuestionController::class, 'index'])->name('programs.questions.index');
            Route::get('/programs/{program}/questions/export', [\App\Http\Controllers\TrainingQuestionController::class, 'export'])->name('programs.questions.export');
            Route::post('/programs/{program}/questions/import', [\App\Http\Controllers\TrainingQuestionController::class, 'import'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.questions.import');
            Route::post('/programs/{program}/questions', [\App\Http\Controllers\TrainingQuestionController::class, 'store'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.questions.store');
            Route::delete('/programs/{program}/questions/{question}', [\App\Http\Controllers\TrainingQuestionController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.questions.destroy');

            Route::resource('programs', TrainingProgramController::class)->except(['destroy'])->parameters(['programs' => 'program'])
                ->middlewareFor(['create', 'store', 'edit', 'update'], 'menu.edit:'.MenuKey::Training->value);
            Route::delete('/programs/{program}', [TrainingProgramController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.destroy');

            Route::get('/programs/{program}/sessions/create', [TrainingSessionController::class, 'create'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.sessions.create');
            Route::post('/programs/{program}/sessions', [TrainingSessionController::class, 'store'])->middleware('menu.edit:'.MenuKey::Training->value)->name('programs.sessions.store');

            Route::get('/sessions/{trainingSession}', [TrainingSessionController::class, 'show'])->name('sessions.show');
            Route::get('/sessions/{trainingSession}/edit', [TrainingSessionController::class, 'edit'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.edit');
            Route::put('/sessions/{trainingSession}', [TrainingSessionController::class, 'update'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.update');
            Route::delete('/sessions/{trainingSession}', [TrainingSessionController::class, 'destroy'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.destroy');

            Route::post('/sessions/{trainingSession}/participants', [TrainingSessionController::class, 'addParticipant'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.participants.store');
            Route::put('/sessions/{trainingSession}/participants/{participant}', [TrainingSessionController::class, 'updateParticipant'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.participants.update');
            Route::delete('/sessions/{trainingSession}/participants/{participant}', [TrainingSessionController::class, 'removeParticipant'])->middleware('menu.edit:'.MenuKey::Training->value)->name('sessions.participants.destroy');
            Route::get('/sessions/{trainingSession}/participants/{participant}/quiz', [TrainingSessionController::class, 'showParticipantQuiz'])->name('sessions.participants.quiz');
        });
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
    });

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewSafety->value])
    ->prefix('safety')
    ->name('safety.')
    ->group(function () {
        Route::get('/lototo', [LototoController::class, 'index'])->name('lototo.index');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
