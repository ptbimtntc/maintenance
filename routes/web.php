<?php

use App\Enums\PermissionName;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSkillAssessmentController;
use App\Http\Controllers\JobDescriptionController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\PositionSkillRequirementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SkillMatrixController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('employees', EmployeeController::class);
    Route::post('/employees/{employee}/skill-assessments', [EmployeeSkillAssessmentController::class, 'store'])->name('employees.skill-assessments.store');
    Route::delete('/employees/{employee}/skill-assessments/{assessment}', [EmployeeSkillAssessmentController::class, 'destroy'])->name('employees.skill-assessments.destroy');
});

Route::pattern('type', implode('|', array_keys(config('master_data'))));

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ManageMasterData->value])
    ->prefix('organization')
    ->name('organization.')
    ->group(function () {
        Route::get('/', [MasterDataController::class, 'landing'])->name('landing');
        Route::get('/{type}', [MasterDataController::class, 'index'])->name('index');
        Route::get('/{type}/create', [MasterDataController::class, 'create'])->name('create');
        Route::post('/{type}', [MasterDataController::class, 'store'])->name('store');
        Route::get('/{type}/{id}/edit', [MasterDataController::class, 'edit'])->name('edit');
        Route::put('/{type}/{id}', [MasterDataController::class, 'update'])->name('update');
        Route::delete('/{type}/{id}', [MasterDataController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified'])->prefix('skills')->name('skills.')->group(function () {
    Route::get('/', function () {
        return view('skills.landing');
    })->name('landing');

    Route::middleware('can:'.PermissionName::ManageSkills->value)->prefix('positions')->name('positions.')->group(function () {
        Route::get('/', [PositionSkillRequirementController::class, 'index'])->name('index');
        Route::get('/{position}/edit', [PositionSkillRequirementController::class, 'edit'])->name('edit');
        Route::post('/{position}/requirements', [PositionSkillRequirementController::class, 'store'])->name('requirements.store');
        Route::delete('/{position}/requirements/{requirement}', [PositionSkillRequirementController::class, 'destroy'])->name('requirements.destroy');
    });
});

Route::middleware(['auth', 'verified', 'can:'.PermissionName::ViewSkillMatrix->value])
    ->get('/skill-matrix', [SkillMatrixController::class, 'index'])
    ->name('skill-matrix.index');

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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
