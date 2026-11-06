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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request, ScopeAccess $scope): JsonResponse
    {
        $schoolIds = $scope->schoolsFor($request->user())->select('id');
        $query = Student::whereHas('enrollments', fn ($builder) => $builder->whereIn('school_id', $schoolIds));
        if ($request->filled('school_id')) {
            abort_unless($scope->schoolsFor($request->user())->whereKey($request->integer('school_id'))->exists(), 403);
            $query->whereHas('enrollments', fn ($builder) => $builder->where('school_id', $request->integer('school_id')));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')->orWhere('nisn', 'like', '%'.$search.'%');
            });
        }

        return response()->json(['data' => $query->with(['enrollments' => fn ($builder) => $builder
            ->whereIn('school_id', $schoolIds)->whereNull('ends_on')->with(['schoolClass', 'academicYear'])])
            ->orderBy('name')->paginate(50)]);
    }

    public function store(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'nisn' => ['nullable', 'string', 'max:20', 'unique:students,nisn'],
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', 'string', 'max:16'],
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
        $this->assertClass($data['school_class_id'] ?? null, $school);

        $student = DB::transaction(function () use ($data, $school): Student {
            $student = Student::create(collect($data)->only(['nisn', 'name', 'birth_date', 'sex'])->all());
            StudentEnrollment::create([
                'student_id' => $student->id,
                'school_id' => $school->id,
                'school_class_id' => $data['school_class_id'] ?? null,
                'academic_year_id' => $data['academic_year_id'],
                'starts_on' => $data['starts_on'],
            ]);

            return $student;
        });
        $audit->record($request, 'student.created', $student, metadata: ['school_id' => $school->id]);

        return response()->json(['data' => $student->load('enrollments')], 201);
    }

    public function update(Request $request, Student $student, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        abort_unless($student->enrollments()->whereNull('ends_on')->get()->contains(
            fn (StudentEnrollment $enrollment) => $scope->canAccessSchool($request->user(), $enrollment->school),
        ), 403, 'Akses siswa tidak diizinkan.');

        $data = $request->validate([
            'nisn' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('students', 'nisn')->ignore($student->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'sex' => ['sometimes', 'nullable', 'string', 'max:16'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $student->update($data);
        $audit->record($request, 'student.updated', $student, metadata: ['fields' => array_keys($data)]);

        return response()->json(['data' => $student->refresh()]);
    }

    private function assertClass(?int $classId, School $school): void
    {
        if ($classId === null) {
            return;
        }

        $class = SchoolClass::findOrFail($classId);
        abort_unless($class->school_id === $school->id, 422, 'Kelas bukan milik sekolah tersebut.');
        abort_unless($class->is_active && $class->academic_level === $school->level, 422, 'Kelas tidak aktif atau jenjang tidak sesuai.');
    }
}
