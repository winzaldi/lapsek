<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\ExaminationAccess;
use App\Services\ScopeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthHistoryController extends Controller
{
    public function index(
        Request $request,
        Student $student,
        ExaminationAccess $access,
        ScopeAccess $scope,
        AuditLogger $audit,
    ): JsonResponse {
        $filters = $request->validate([
            'semester_id' => ['sometimes', 'integer', 'exists:semesters,id'],
            'academic_year' => ['sometimes', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['sometimes', 'integer', 'between:1,2'],
            'school_id' => ['sometimes', 'integer', 'exists:schools,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $access->assertCanViewStudentHistory($request->user(), $student);
        $visibleHistory = Examination::query()->where('student_id', $student->id);
        if ($request->user()->hasRole('student') || ! $request->user()->hasRole('puskesmas_operator')) {
            $visibleHistory->where('status', 'published');
        }
        if (! $request->user()->hasRole('student')) {
            $visibleHistory->whereIn('school_id', $scope->schoolsFor($request->user())->select('id'));
        }
        $availablePeriods = (clone $visibleHistory)
            ->join('semesters as history_semesters', 'history_semesters.id', '=', 'examinations.semester_id')
            ->join('academic_years as history_years', 'history_years.id', '=', 'history_semesters.academic_year_id')
            ->select([
                'history_years.name as academic_year',
                'history_semesters.number as semester',
                'history_semesters.id as semester_id',
            ])
            ->distinct()
            ->orderByDesc('history_years.name')
            ->orderByDesc('history_semesters.number')
            ->get();
        $query = Examination::with(['observations', 'school', 'schoolClass', 'semester.academicYear'])
            ->where('student_id', $student->id)
            ->orderByDesc('examined_at');
        if ($request->user()->hasRole('student')) {
            $query->where('status', 'published');
        } else {
            $query->whereIn('school_id', $scope->schoolsFor($request->user())->select('id'));
            if (! $request->user()->hasRole('puskesmas_operator')) {
                $query->where('status', 'published');
            }
        }
        if (isset($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }
        if (isset($filters['school_id'])) {
            abort_if(
                ! $request->user()->hasRole('student') && ! $scope->schoolsFor($request->user())->whereKey($filters['school_id'])->exists(),
                403,
                'Akses sekolah tidak diizinkan.',
            );
            $query->where('school_id', $filters['school_id']);
        }
        if (isset($filters['academic_year'])) {
            $query->whereHas('semester.academicYear', fn ($year) => $year->where('name', $filters['academic_year']));
        }
        if (isset($filters['semester'])) {
            $query->whereHas('semester', fn ($semester) => $semester->where('number', $filters['semester']));
        }
        $examinations = $query->paginate(50);
        $audit->record($request, 'student.health_history_viewed', 'Student', $student->id, [
            'result_count' => $examinations->count(),
        ]);

        return response()->json([
            'data' => $examinations->through(fn (Examination $examination) => [
                'id' => $examination->id,
                'school' => [
                    'id' => $examination->school_id,
                    'name' => $examination->school_name_snapshot,
                ],
                'school_class' => $examination->school_class_id ? [
                    'id' => $examination->school_class_id,
                    'name' => $examination->class_name_snapshot,
                ] : null,
                'semester' => $examination->semester,
                'examined_at' => $examination->examined_at?->toDateString(),
                'examiner_name' => $examination->examiner_name,
                'status' => $examination->status,
                'version' => $examination->version,
                'bmi' => $examination->bmi,
                'bmi_calculation_version' => $examination->bmi_calculation_version,
                'observations' => $examination->observations->keyBy('indicator_key')->map(fn ($observation) => [
                    'status' => $observation->status,
                    'value' => $observation->value,
                ]),
            ]),
            'available_periods' => $availablePeriods,
        ]);
    }
}
