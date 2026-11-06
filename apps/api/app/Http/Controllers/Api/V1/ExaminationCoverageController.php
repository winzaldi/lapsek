<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\Semester;
use App\Models\StudentEnrollment;
use App\Services\AuditLogger;
use App\Services\ScopeAccess;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExaminationCoverageController extends Controller
{
    public function index(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()->hasRole('puskesmas_operator'), 403, 'Daftar sasaran hanya tersedia bagi Operator Puskesmas.');
        $filters = $request->validate([
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'school_id' => ['sometimes', 'integer', 'exists:schools,id'],
            'school_class_id' => ['sometimes', 'integer', 'exists:school_classes,id'],
            'status' => ['sometimes', Rule::in(['checked', 'not_checked'])],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);
        $semester = Semester::findOrFail($filters['semester_id']);
        $schoolQuery = $scope->schoolsFor($request->user());
        if (isset($filters['school_id'])) {
            abort_unless($schoolQuery->whereKey($filters['school_id'])->exists(), 403, 'Akses sekolah tidak diizinkan.');
        }

        $baseQuery = StudentEnrollment::query()
            ->leftJoin('examinations', function (JoinClause $join) use ($semester): void {
                $join->on('examinations.student_id', '=', 'student_enrollments.student_id')
                    ->on('examinations.school_id', '=', 'student_enrollments.school_id')
                    ->where('examinations.semester_id', '=', $semester->id);
            })
            ->where('student_enrollments.academic_year_id', $semester->academic_year_id)
            ->whereIn('student_enrollments.school_id', $scope->schoolsFor($request->user())->select('id'))
            ->whereDate('student_enrollments.starts_on', '<=', $semester->ends_on->toDateString())
            ->where(fn ($query) => $query->whereNull('student_enrollments.ends_on')
                ->orWhereDate('student_enrollments.ends_on', '>=', $semester->starts_on->toDateString()));

        if (isset($filters['school_id'])) {
            $baseQuery->where('student_enrollments.school_id', $filters['school_id']);
        }
        if (isset($filters['school_class_id'])) {
            $baseQuery->where('student_enrollments.school_class_id', $filters['school_class_id']);
        }
        $total = (clone $baseQuery)->count('student_enrollments.id');
        $checked = (clone $baseQuery)->whereNotNull('examinations.id')->count('student_enrollments.id');
        $notChecked = $total - $checked;
        if (($filters['status'] ?? null) === 'checked') {
            $baseQuery->whereNotNull('examinations.id');
        } elseif (($filters['status'] ?? null) === 'not_checked') {
            $baseQuery->whereNull('examinations.id');
        }

        $rows = $baseQuery
            ->select([
                'student_enrollments.*',
                'examinations.id as examination_id',
                'examinations.status as examination_status',
            ])
            ->with(['student:id,nisn,name,birth_date', 'school:id,name', 'schoolClass:id,name,grade'])
            ->orderBy('student_enrollments.school_id')
            ->orderBy('student_enrollments.school_class_id')
            ->orderBy('student_enrollments.student_id')
            ->paginate($filters['per_page'] ?? 50)
            ->through(fn (StudentEnrollment $enrollment) => [
                'student' => $enrollment->student,
                'school' => $enrollment->school,
                'school_class' => $enrollment->schoolClass,
                'examination_id' => $enrollment->examination_id,
                'examination_status' => $enrollment->examination_status,
                'coverage_status' => $enrollment->examination_id ? 'checked' : 'not_checked',
            ]);
        $audit->record($request, 'examination.coverage_viewed', Examination::class, metadata: [
            'semester_id' => $semester->id,
            'school_id' => $filters['school_id'] ?? null,
            'total' => $total,
            'checked' => $checked,
        ]);

        return response()->json([
            'summary' => [
                'total' => $total,
                'checked' => $checked,
                'not_checked' => $notChecked,
                'coverage_percent' => $total === 0 ? 0 : round(($checked / $total) * 100, 2),
            ],
            'data' => $rows,
        ]);
    }
}
