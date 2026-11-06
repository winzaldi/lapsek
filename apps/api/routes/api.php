<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\ExaminationController;
use App\Http\Controllers\Api\V1\ExaminationCoverageController;
use App\Http\Controllers\Api\V1\HealthCenterController;
use App\Http\Controllers\Api\V1\HealthHistoryController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportExportController;
use App\Http\Controllers\Api\V1\SchoolClassController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SemesterController;
use App\Http\Controllers\Api\V1\StudentAccountLinkController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentImportController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('api.v1.health');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/health-centers', [HealthCenterController::class, 'index'])->middleware('role:system_admin');
        Route::post('/health-centers', [HealthCenterController::class, 'store'])->middleware('role:system_admin');
        Route::patch('/health-centers/{healthCenter}', [HealthCenterController::class, 'update'])->middleware('role:system_admin');

        Route::get('/schools', [SchoolController::class, 'index'])->middleware('permission:schools.view');
        Route::post('/schools', [SchoolController::class, 'store'])->middleware('role:system_admin');
        Route::patch('/schools/{school}', [SchoolController::class, 'update'])->middleware('role:system_admin');

        Route::get('/academic-years', [AcademicYearController::class, 'index'])->middleware('permission:academic.view');
        Route::post('/academic-years', [AcademicYearController::class, 'store'])->middleware('role:system_admin');
        Route::get('/semesters', [SemesterController::class, 'index'])->middleware('permission:academic.view');
        Route::post('/semesters', [SemesterController::class, 'store'])->middleware('role:system_admin');

        Route::get('/classes', [SchoolClassController::class, 'index'])->middleware('permission:academic.view');
        Route::post('/classes', [SchoolClassController::class, 'store'])->middleware('permission:students.manage');
        Route::patch('/classes/{class}', [SchoolClassController::class, 'update'])->middleware('permission:students.manage');

        Route::get('/students', [StudentController::class, 'index'])->middleware('permission:students.view');
        Route::post('/students', [StudentController::class, 'store'])->middleware('permission:students.manage');
        Route::patch('/students/{student}', [StudentController::class, 'update'])->middleware('permission:students.manage');
        Route::get('/enrollments', [EnrollmentController::class, 'index'])->middleware('permission:students.view');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->middleware('permission:enrollments.manage');
        Route::patch('/enrollments/{enrollment}/close', [EnrollmentController::class, 'close'])->middleware('permission:enrollments.manage');
        Route::post('/enrollments/{enrollment}/transfer', [EnrollmentController::class, 'transfer'])->middleware('permission:enrollments.manage');

        Route::post('/student-account-links', [StudentAccountLinkController::class, 'store'])->middleware('role:system_admin');
        Route::post('/student-account-links/{studentAccountLink}/verify', [StudentAccountLinkController::class, 'verify'])
            ->middleware('permission:student-links.verify');

        Route::get('/users', [UserController::class, 'index'])->middleware('role:system_admin');
        Route::post('/users', [UserController::class, 'store'])->middleware('role:system_admin');
        Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->middleware('role:system_admin');
        Route::patch('/users/{user}/status', [UserController::class, 'setStatus'])->middleware('role:system_admin');

        Route::post('/student-imports/preview', [StudentImportController::class, 'preview'])->middleware('permission:imports.manage');
        Route::post('/student-imports/commit', [StudentImportController::class, 'commit'])->middleware('permission:imports.manage');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('role:system_admin');

        Route::get('/examinations', [ExaminationController::class, 'index'])->middleware('permission:examinations.view');
        Route::get('/examination-coverage', [ExaminationCoverageController::class, 'index'])->middleware('permission:examinations.view');
        Route::get('/reports/summary', [ReportController::class, 'summary'])->middleware('permission:examinations.view');
        Route::get('/reports/examinations.csv', [ReportController::class, 'exportExaminations'])->middleware('permission:examinations.view');
        Route::get('/reports/exports', [ReportExportController::class, 'index'])->middleware('permission:examinations.view')->name('api.v1.report-exports.index');
        Route::post('/reports/exports', [ReportExportController::class, 'store'])->middleware('permission:examinations.view')->name('api.v1.report-exports.store');
        Route::get('/reports/exports/{reportExport}', [ReportExportController::class, 'show'])->middleware('permission:examinations.view')->name('api.v1.report-exports.show');
        Route::get('/reports/exports/{reportExport}/download', [ReportExportController::class, 'download'])->middleware('permission:examinations.view')->name('api.v1.report-exports.download');
        Route::post('/examinations', [ExaminationController::class, 'store'])->middleware('permission:examinations.manage');
        Route::get('/examinations/{examination}', [ExaminationController::class, 'show'])->middleware('permission:examinations.view');
        Route::patch('/examinations/{examination}', [ExaminationController::class, 'update'])->middleware('permission:examinations.manage');
        Route::post('/examinations/{examination}/finalize', [ExaminationController::class, 'finalize'])->middleware('permission:examinations.finalize');
        Route::post('/examinations/{examination}/publish', [ExaminationController::class, 'publish'])->middleware('permission:examinations.publish');
        Route::post('/examinations/{examination}/corrections', [ExaminationController::class, 'correct'])->middleware('permission:examinations.correct');
        Route::get('/examinations/{examination}/revisions', [ExaminationController::class, 'revisions'])->middleware('permission:examinations.view');
        Route::get('/students/{student}/health-history', [HealthHistoryController::class, 'index']);
    });
});
