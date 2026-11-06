<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\AuditLogger;
use App\Services\ScopeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    public function index(Request $request, ScopeAccess $scope): JsonResponse
    {
        $query = StudentEnrollment::with(['student:id,nisn,name,birth_date', 'schoolClass:id,name,grade', 'academicYear:id,name'])
            ->whereIn('school_id', $scope->schoolsFor($request->user())->select('id'));
        foreach (['school_id', 'academic_year_id', 'school_class_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }

        return response()->json(['data' => $query->orderByDesc('starts_on')->paginate(50)]);
    }

    public function store(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'starts_on' => ['required', 'date'],
        ]);
        $school = School::findOrFail($data['school_id']);
        $scope->assertSchool($request->user(), $school);
        $year = AcademicYear::findOrFail($data['academic_year_id']);
        abort_unless($year->school_id === $school->id, 422, 'Tahun ajaran bukan milik sekolah tersebut.');
        abort_if(
            $data['starts_on'] < $year->starts_on->toDateString() || $data['starts_on'] > $year->ends_on->toDateString(),
            422,
            'Tanggal enrollment harus berada di dalam tahun ajaran.',
        );
        if ($data['school_class_id'] ?? null) {
            $class = SchoolClass::findOrFail($data['school_class_id']);
            abort_unless($class->school_id === $school->id, 422, 'Kelas bukan milik sekolah tersebut.');
            abort_unless($class->is_active && $class->academic_level === $school->level, 422, 'Kelas tidak aktif atau jenjang tidak sesuai.');
        }

        $enrollment = DB::transaction(function () use ($data): StudentEnrollment {
            $student = Student::whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
            abort_if(
                $student->enrollments()->whereNull('ends_on')->exists(),
                422,
                'Siswa masih memiliki enrollment aktif. Akhiri enrollment sebelumnya sebelum memindahkan.',
            );

            return StudentEnrollment::create($data);
        });
        $audit->record($request, 'enrollment.created', $enrollment, metadata: ['school_id' => $school->id]);

        return response()->json(['data' => $enrollment->load(['student', 'schoolClass', 'academicYear'])], 201);
    }

    public function close(Request $request, StudentEnrollment $enrollment, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $scope->assertSchool($request->user(), $enrollment->school);
        $data = $request->validate(['ends_on' => ['required', 'date', 'after_or_equal:'.$enrollment->starts_on->toDateString()]]);
        $closedEnrollment = DB::transaction(function () use ($data, $enrollment): StudentEnrollment {
            Student::whereKey($enrollment->student_id)->lockForUpdate()->firstOrFail();
            $current = StudentEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_if($current->ends_on !== null, 422, 'Enrollment sudah ditutup.');
            $current->update(['ends_on' => $data['ends_on']]);

            return $current;
        });
        $audit->record($request, 'enrollment.closed', $closedEnrollment);

        return response()->json(['data' => $closedEnrollment->refresh()]);
    }

    public function transfer(Request $request, StudentEnrollment $enrollment, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $scope->assertSchool($request->user(), $enrollment->school);
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'starts_on' => ['required', 'date', 'after:'.$enrollment->starts_on->toDateString()],
        ]);
        abort_if($enrollment->ends_on !== null, 422, 'Enrollment sudah ditutup.');

        $school = School::findOrFail($data['school_id']);
        $scope->assertSchool($request->user(), $school);
        $year = AcademicYear::findOrFail($data['academic_year_id']);
        abort_unless($year->school_id === $school->id, 422, 'Tahun ajaran bukan milik sekolah tujuan.');
        abort_if(
            $data['starts_on'] < $year->starts_on->toDateString() || $data['starts_on'] > $year->ends_on->toDateString(),
            422,
            'Tanggal perpindahan harus berada di dalam tahun ajaran tujuan.',
        );
        if ($data['school_class_id'] ?? null) {
            $class = SchoolClass::findOrFail($data['school_class_id']);
            abort_unless($class->school_id === $school->id, 422, 'Kelas bukan milik sekolah tujuan.');
            abort_unless($class->is_active && $class->academic_level === $school->level, 422, 'Kelas tidak aktif atau jenjang tidak sesuai.');
        }

        $newEnrollment = DB::transaction(function () use ($data, $enrollment, $school): StudentEnrollment {
            $student = Student::whereKey($enrollment->student_id)->lockForUpdate()->firstOrFail();
            $current = StudentEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_if($current->ends_on !== null, 422, 'Enrollment sudah ditutup.');
            abort_if(
                $student->enrollments()->whereNull('ends_on')->where('id', '<>', $current->id)->exists(),
                422,
                'Siswa memiliki enrollment aktif lain.',
            );

            $current->update(['ends_on' => Carbon::parse($data['starts_on'])->subDay()->toDateString()]);

            return StudentEnrollment::create([
                'student_id' => $student->id,
                'school_id' => $school->id,
                'school_class_id' => $data['school_class_id'] ?? null,
                'academic_year_id' => $data['academic_year_id'],
                'starts_on' => $data['starts_on'],
            ]);
        });
        $audit->record($request, 'enrollment.transferred_from', $enrollment, metadata: ['to_school_id' => $school->id]);
        $audit->record($request, 'enrollment.transferred_to', $newEnrollment, metadata: ['from_school_id' => $enrollment->school_id]);

        return response()->json(['data' => $newEnrollment->load(['student', 'schoolClass', 'academicYear'])], 201);
    }
}
