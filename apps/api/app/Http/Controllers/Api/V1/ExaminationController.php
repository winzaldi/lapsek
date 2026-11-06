<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\ExaminationRevision;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ExaminationAccess;
use App\Services\ExaminationIndicatorSchema;
use App\Services\ScopeAccess;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExaminationController extends Controller
{
    public function index(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $query = Examination::query()
            ->with(['observations', 'student:id,nisn,name,birth_date', 'school:id,name', 'schoolClass:id,name,grade', 'semester:id,academic_year_id,number', 'semester.academicYear:id,name'])
            ->whereIn('school_id', $scope->schoolsFor($request->user())->select('id'));

        if (! $request->user()->hasRole('puskesmas_operator')) {
            $query->where('status', 'published');
        }
        foreach (['school_id', 'student_id', 'semester_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }
        if ($request->filled('status') && $request->user()->hasRole('puskesmas_operator')) {
            $query->where('status', $request->string('status')->toString());
        }

        $examinations = $query->orderByDesc('examined_at')->paginate(50)
            ->through(fn (Examination $examination) => $this->present($examination));
        $audit->record($request, 'examination.list_viewed', Examination::class, metadata: [
            'school_id' => $request->input('school_id'),
            'semester_id' => $request->input('semester_id'),
        ]);

        return response()->json(['data' => $examinations]);
    }

    public function store(
        Request $request,
        ScopeAccess $scope,
        ExaminationAccess $access,
        ExaminationIndicatorSchema $schema,
        AuditLogger $audit,
    ): JsonResponse {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'examiner_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'examined_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'examiner_name' => ['required', 'string', 'max:255', 'not_regex:/<[^>]*>/'],
            'observations' => ['sometimes', 'required', 'array'],
        ]);
        $observations = $schema->validate($data['observations'] ?? []);
        $student = Student::findOrFail($data['student_id']);
        $semester = Semester::with('academicYear.school')->findOrFail($data['semester_id']);
        $year = $semester->academicYear;
        abort_unless(
            $data['examined_at'] >= $semester->starts_on->toDateString()
                && $data['examined_at'] <= $semester->ends_on->toDateString(),
            422,
            'Tanggal pemeriksaan harus berada di dalam periode semester.',
        );
        $enrollment = StudentEnrollment::with('school')
            ->where('student_id', $student->id)
            ->where('school_id', $year->school_id)
            ->where('academic_year_id', $year->id)
            ->whereDate('starts_on', '<=', $data['examined_at'])
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $data['examined_at']))
            ->first();
        abort_unless($enrollment, 422, 'Siswa tidak memiliki enrollment pada sekolah dan tahun ajaran semester ini.');
        $access->assertCanManage($request->user(), $enrollment->school);
        $this->assertExaminer($data['examiner_user_id'] ?? null, $enrollment->school, $scope);
        abort_if(
            Examination::where('student_id', $student->id)->where('semester_id', $semester->id)->exists(),
            409,
            'Pemeriksaan untuk siswa dan semester ini sudah ada.',
        );

        try {
            $examination = DB::transaction(function () use ($data, $enrollment, $semester, $year, $request, $observations, $audit, $access): Examination {
                $lockedEnrollment = StudentEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
                abort_unless(
                    $lockedEnrollment->academic_year_id === $semester->academic_year_id
                        && $lockedEnrollment->school_id === $year->school_id
                        && $lockedEnrollment->starts_on->toDateString() <= $data['examined_at']
                        && ($lockedEnrollment->ends_on === null || $lockedEnrollment->ends_on->toDateString() >= $data['examined_at']),
                    422,
                    'Enrollment siswa berubah; periksa kembali sasaran pemeriksaan.',
                );
                $school = School::whereKey($lockedEnrollment->school_id)->lockForUpdate()->firstOrFail();
                $access->assertCanManage($request->user(), $school);
                $examination = Examination::create([
                    'student_id' => $data['student_id'],
                    'school_id' => $lockedEnrollment->school_id,
                    'school_class_id' => $lockedEnrollment->school_class_id,
                    'semester_id' => $semester->id,
                    'examiner_user_id' => $data['examiner_user_id'] ?? null,
                    'school_name_snapshot' => $school->name,
                    'class_name_snapshot' => $lockedEnrollment->school_class_id
                        ? SchoolClass::findOrFail($lockedEnrollment->school_class_id)->name
                        : null,
                    'examined_at' => $data['examined_at'],
                    'examiner_name' => $data['examiner_name'],
                    'entered_by_user_id' => $request->user()->id,
                    'status' => 'draft',
                ]);
                $this->saveObservations($examination, $observations, $request->user()->id);
                $this->calculateBmi($examination);
                $audit->record($request, 'examination.draft_created', $examination, metadata: [
                    'school_id' => $examination->school_id,
                    'semester_id' => $examination->semester_id,
                ]);

                return $examination;
            });
        } catch (QueryException $exception) {
            $message = $exception->getMessage();
            $isDuplicateExamination = in_array((string) $exception->getCode(), ['23000', '23505'], true)
                && (
                    str_contains($message, 'examinations_student_id_semester_id_unique')
                    || str_contains($message, 'examinations.student_id, examinations.semester_id')
                );
            if ($isDuplicateExamination) {
                abort(409, 'Pemeriksaan untuk siswa dan semester ini sudah ada.');
            }

            throw $exception;
        }

        return response()->json(['data' => $this->present($examination->fresh([
            'observations', 'student', 'school', 'schoolClass', 'semester.academicYear',
        ]))], 201);
    }

    public function show(
        Request $request,
        Examination $examination,
        ExaminationAccess $access,
        AuditLogger $audit,
    ): JsonResponse {
        $access->assertCanView($request->user(), $examination);

        $audit->record($request, 'examination.viewed', $examination);

        return response()->json(['data' => $this->present($examination->load([
            'observations', 'student', 'school', 'schoolClass', 'semester.academicYear',
        ]))]);
    }

    public function update(
        Request $request,
        Examination $examination,
        ExaminationAccess $access,
        ScopeAccess $scope,
        ExaminationIndicatorSchema $schema,
        AuditLogger $audit,
    ): JsonResponse {
        $access->assertCanManage($request->user(), $examination->school);
        abort_unless($examination->status === 'draft', 409, 'Hanya draf yang dapat diubah langsung.');
        $data = $request->validate([
            'examined_at' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'examiner_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'examiner_name' => ['sometimes', 'required', 'string', 'max:255', 'not_regex:/<[^>]*>/'],
            'observations' => ['sometimes', 'required', 'array'],
        ]);
        if (isset($data['examined_at'])) {
            $semester = $examination->semester;
            abort_unless(
                $data['examined_at'] >= $semester->starts_on->toDateString()
                    && $data['examined_at'] <= $semester->ends_on->toDateString(),
                422,
                'Tanggal pemeriksaan harus berada di dalam periode semester.',
            );
        }
        if (array_key_exists('examiner_user_id', $data)) {
            $this->assertExaminer($data['examiner_user_id'], $examination->school, $scope);
        }
        $observations = $schema->validate($data['observations'] ?? []);
        unset($data['observations']);

        DB::transaction(function () use ($data, $observations, $examination, $request, $audit): void {
            $locked = Examination::whereKey($examination->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'draft', 409, 'Draf sudah berubah status.');
            if (isset($data['examined_at'])) {
                $semester = $locked->semester;
                abort_unless(
                    $data['examined_at'] >= $semester->starts_on->toDateString()
                        && $data['examined_at'] <= $semester->ends_on->toDateString(),
                    422,
                    'Tanggal pemeriksaan harus berada di dalam periode semester.',
                );
                $this->assertEnrollmentOnDate($locked, $data['examined_at']);
            }
            $locked->update($data);
            $this->saveObservations($locked, $observations, $request->user()->id);
            $this->calculateBmi($locked);
            $audit->record($request, 'examination.draft_updated', $locked, metadata: [
                'fields' => array_keys($data + ['observations' => $observations]),
            ]);
        });

        return response()->json(['data' => $this->present($examination->fresh([
            'observations', 'student', 'school', 'schoolClass', 'semester.academicYear',
        ]))]);
    }

    public function finalize(
        Request $request,
        Examination $examination,
        ExaminationAccess $access,
        ExaminationIndicatorSchema $schema,
        AuditLogger $audit,
    ): JsonResponse {
        $access->assertCanManage($request->user(), $examination->school, 'examinations.finalize');
        $finalized = DB::transaction(function () use ($request, $examination, $schema, $audit): Examination {
            $locked = Examination::with('observations')->whereKey($examination->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'draft', 409, 'Hanya draf yang dapat difinalisasi.');
            $missing = $schema->missing($locked->observations->keyBy('indicator_key')->all());
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'observations' => ['Tentukan nilai atau status tidak diperiksa untuk seluruh indikator.'],
                    'missing_indicators' => $missing,
                ]);
            }
            $locked->update([
                'status' => 'finalized',
                'version' => 1,
                'finalized_at' => now(),
                'published_at' => null,
            ]);
            $this->saveRevision($locked, $request->user()->id, null);
            $audit->record($request, 'examination.finalized', $locked);

            return $locked->fresh(['observations', 'student', 'school', 'schoolClass', 'semester.academicYear']);
        });

        return response()->json(['data' => $this->present($finalized)]);
    }

    public function publish(
        Request $request,
        Examination $examination,
        ExaminationAccess $access,
        AuditLogger $audit,
    ): JsonResponse {
        $access->assertCanManage($request->user(), $examination->school, 'examinations.publish');
        $published = DB::transaction(function () use ($request, $examination, $audit): Examination {
            $locked = Examination::whereKey($examination->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'finalized', 409, 'Hanya pemeriksaan final yang dapat dipublikasikan.');
            $locked->update(['status' => 'published', 'published_at' => now()]);
            $audit->record($request, 'examination.published', $locked);

            return $locked->fresh(['observations', 'student', 'school', 'schoolClass', 'semester.academicYear']);
        });

        return response()->json(['data' => $this->present($published)]);
    }

    public function correct(
        Request $request,
        Examination $examination,
        ExaminationAccess $access,
        ExaminationIndicatorSchema $schema,
        AuditLogger $audit,
    ): JsonResponse {
        $access->assertCanManage($request->user(), $examination->school, 'examinations.correct');
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000', 'not_regex:/<[^>]*>/'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'observations' => ['required', 'array', 'min:1'],
        ]);
        $observations = $schema->validate($data['observations']);
        $corrected = DB::transaction(function () use ($request, $examination, $data, $observations, $schema, $audit): Examination {
            $locked = Examination::with('observations')->whereKey($examination->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['finalized', 'published'], true), 409, 'Hanya pemeriksaan final atau terpublikasi yang dapat dikoreksi.');
            abort_unless($locked->version === $data['expected_version'], 409, 'Versi pemeriksaan sudah berubah; muat ulang sebelum mengoreksi.');

            $current = $locked->observations->keyBy('indicator_key')->map(fn ($observation) => [
                'status' => $observation->status,
                'value' => $observation->value,
            ])->all();
            $merged = array_replace($current, $observations);
            if ($missing = $schema->missing($merged)) {
                throw ValidationException::withMessages([
                    'observations' => ['Semua indikator harus tetap memiliki nilai atau status tidak diperiksa.'],
                    'missing_indicators' => $missing,
                ]);
            }

            $this->saveObservations($locked, $observations, $request->user()->id);
            $this->calculateBmi($locked);
            $locked->update([
                'status' => 'finalized',
                'published_at' => null,
                'finalized_at' => now(),
                'version' => $locked->version + 1,
            ]);
            $this->saveRevision($locked, $request->user()->id, $data['reason']);
            $audit->record($request, 'examination.corrected', $locked, metadata: ['version' => $locked->version]);

            return $locked->fresh(['observations', 'student', 'school', 'schoolClass', 'semester.academicYear']);
        });

        return response()->json(['data' => $this->present($corrected)]);
    }

    public function revisions(
        Request $request,
        Examination $examination,
        ExaminationAccess $access,
        AuditLogger $audit,
    ): JsonResponse {
        $access->assertCanManage($request->user(), $examination->school, 'examinations.view');

        $revisions = $examination->revisions()->with('actor:id,name')->get();
        $audit->record($request, 'examination.revisions_viewed', $examination);

        return response()->json(['data' => $revisions]);
    }

    private function saveObservations(Examination $examination, array $observations, int $actorId): void
    {
        foreach ($observations as $key => $observation) {
            $examination->observations()->updateOrCreate(
                ['indicator_key' => $key],
                [
                    'status' => $observation['status'],
                    'value' => $observation['value'],
                    'updated_by_user_id' => $actorId,
                ],
            );
        }
    }

    private function calculateBmi(Examination $examination): void
    {
        $observations = $examination->observations()->get()->keyBy('indicator_key');
        $height = $observations->get('height_cm');
        $weight = $observations->get('weight_kg');
        if (! $height || ! $weight || $height->status !== 'recorded' || $weight->status !== 'recorded') {
            $examination->update(['bmi' => null, 'bmi_calculation_version' => null]);

            return;
        }
        $heightMeters = (float) $height->value / 100;
        $bmi = round((float) $weight->value / ($heightMeters * $heightMeters), 2);
        $examination->update(['bmi' => number_format($bmi, 2, '.', ''), 'bmi_calculation_version' => 'bmi-kg-m2-v1']);
    }

    private function saveRevision(Examination $examination, int $actorId, ?string $reason): void
    {
        $examination->load('observations', 'student', 'school', 'schoolClass', 'semester.academicYear');
        $snapshot = [
            'examination' => $examination->only([
                'student_id', 'school_id', 'school_class_id', 'semester_id', 'examined_at',
                'examiner_user_id', 'school_name_snapshot', 'class_name_snapshot',
                'examiner_name', 'entered_by_user_id', 'status', 'version', 'bmi', 'bmi_calculation_version',
                'finalized_at', 'published_at',
            ]),
            'observations' => $examination->observations->keyBy('indicator_key')->map(fn ($observation) => [
                'status' => $observation->status,
                'value' => $observation->value,
            ])->all(),
        ];
        ExaminationRevision::create([
            'examination_id' => $examination->id,
            'version' => $examination->version,
            'actor_user_id' => $actorId,
            'reason' => $reason,
            'snapshot' => $snapshot,
        ]);
    }

    private function assertEnrollmentOnDate(Examination $examination, string $date): void
    {
        $valid = StudentEnrollment::where('student_id', $examination->student_id)
            ->where('school_id', $examination->school_id)
            ->where('academic_year_id', $examination->semester->academic_year_id)
            ->whereDate('starts_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))
            ->lockForUpdate()
            ->first();
        abort_unless($valid, 422, 'Siswa tidak memiliki enrollment pada tanggal pemeriksaan.');
    }

    private function assertExaminer(?int $examinerId, School $school, ScopeAccess $scope): void
    {
        if ($examinerId === null) {
            return;
        }
        $examiner = User::findOrFail($examinerId);
        abort_unless(
            $examiner->is_active && $examiner->hasRole('puskesmas_operator')
                && $scope->canAccessSchool($examiner, $school),
            422,
            'Akun petugas pemeriksa harus aktif dan memiliki cakupan ke sekolah tersebut.',
        );
    }

    private function present(Examination $examination): array
    {
        return [
            'id' => $examination->id,
            'student' => $examination->student,
            'school' => [
                'id' => $examination->school_id,
                'name' => $examination->school_name_snapshot,
            ],
            'school_class' => $examination->school_class_id ? [
                'id' => $examination->school_class_id,
                'name' => $examination->class_name_snapshot,
            ] : null,
            'semester' => $examination->semester,
            'examiner_user_id' => $examination->examiner_user_id,
            'examined_at' => $examination->examined_at?->toDateString(),
            'examiner_name' => $examination->examiner_name,
            'entered_by_user_id' => $examination->entered_by_user_id,
            'status' => $examination->status,
            'version' => $examination->version,
            'bmi' => $examination->bmi,
            'bmi_calculation_version' => $examination->bmi_calculation_version,
            'finalized_at' => $examination->finalized_at,
            'published_at' => $examination->published_at,
            'observations' => $examination->observations->keyBy('indicator_key')->map(fn ($observation) => [
                'status' => $observation->status,
                'value' => $observation->value,
            ]),
        ];
    }
}
