<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Services\AuditLogger;
use App\Services\ExaminationIndicatorSchema;
use App\Services\ScopeAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const SMALL_GROUP_THRESHOLD = 5;

    private const MAX_EXPORT_ROWS = 5000;

    public function summary(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $schoolIds = $scope->schoolsFor($request->user())->select('id');
        $years = AcademicYear::query()
            ->where('name', $filters['academic_year'])
            ->whereIn('school_id', $schoolIds);
        if (isset($filters['school_id'])) {
            abort_unless((clone $years)->where('school_id', $filters['school_id'])->exists(), 403, 'Akses sekolah atau periode tidak diizinkan.');
            $years->where('school_id', $filters['school_id']);
        }
        if (isset($filters['level'])) {
            $years->whereHas('school', fn (Builder $query) => $query->where('level', $filters['level']));
        }
        $yearIds = $years->select('id');
        $this->assertClassFilter($filters, $schoolIds);

        $base = StudentEnrollment::query()
            ->join('academic_years as report_years', 'report_years.id', '=', 'student_enrollments.academic_year_id')
            ->join('schools as report_schools', 'report_schools.id', '=', 'student_enrollments.school_id')
            ->leftJoin('school_classes as report_classes', 'report_classes.id', '=', 'student_enrollments.school_class_id')
            ->whereIn('student_enrollments.school_id', $schoolIds)
            ->whereIn('student_enrollments.academic_year_id', $yearIds);
        if (isset($filters['school_id'])) {
            $base->where('student_enrollments.school_id', $filters['school_id']);
        }
        if (isset($filters['level'])) {
            $base->where('report_schools.level', $filters['level']);
        }
        if (isset($filters['school_class_id'])) {
            $base->where('student_enrollments.school_class_id', $filters['school_class_id']);
        }
        if (isset($filters['semester'])) {
            $base->join('semesters as report_semesters', function ($join) use ($filters): void {
                $join->on('report_semesters.academic_year_id', '=', 'report_years.id')
                    ->where('report_semesters.number', '=', $filters['semester']);
            })
                ->whereColumn('student_enrollments.starts_on', '<=', 'report_semesters.ends_on')
                ->where(fn ($query) => $query->whereNull('student_enrollments.ends_on')
                    ->orWhereColumn('student_enrollments.ends_on', '>=', 'report_semesters.starts_on'));
        } else {
            $base->whereColumn('student_enrollments.starts_on', '<=', 'report_years.ends_on')
                ->where(fn ($query) => $query->whereNull('student_enrollments.ends_on')
                    ->orWhereColumn('student_enrollments.ends_on', '>=', 'report_years.starts_on'));
        }

        $visibleStatuses = $request->user()->hasRole('puskesmas_operator') || $request->user()->hasRole('system_admin')
            ? ['finalized', 'published']
            : ['published'];
        $examined = DB::table('examinations')
            ->join('semesters as report_exam_semesters', 'report_exam_semesters.id', '=', 'examinations.semester_id')
            ->join('academic_years as report_exam_years', 'report_exam_years.id', '=', 'report_exam_semesters.academic_year_id')
            ->select([
                'examinations.student_id',
                'examinations.school_id',
                'report_exam_years.id as academic_year_id',
            ])
            ->where('report_exam_years.name', $filters['academic_year'])
            ->whereIn('examinations.school_id', $schoolIds)
            ->whereIn('examinations.status', $visibleStatuses)
            ->when(isset($filters['school_id']), fn ($query) => $query->where('examinations.school_id', $filters['school_id']))
            ->when(isset($filters['level']), fn ($query) => $query->whereIn('examinations.school_id', (clone $years)->select('school_id')))
            ->when(isset($filters['semester']), fn ($query) => $query->where('report_exam_semesters.number', $filters['semester']))
            ->groupBy('examinations.student_id', 'examinations.school_id', 'report_exam_years.id');

        $base->leftJoinSub($examined, 'report_examinations', function ($join): void {
            $join->on('report_examinations.student_id', '=', 'student_enrollments.student_id')
                ->on('report_examinations.school_id', '=', 'student_enrollments.school_id')
                ->on('report_examinations.academic_year_id', '=', 'student_enrollments.academic_year_id');
        });

        $total = (int) (clone $base)->selectRaw('COUNT(DISTINCT student_enrollments.id) as aggregate')->value('aggregate');
        $checked = (int) (clone $base)->whereNotNull('report_examinations.student_id')
            ->selectRaw('COUNT(DISTINCT student_enrollments.id) as aggregate')->value('aggregate');
        $summary = [
            'total' => $total,
            'checked' => $checked,
            'not_checked' => max(0, $total - $checked),
            'coverage_percent' => $total === 0 ? 0 : round(($checked / $total) * 100, 2),
        ];

        $bySchool = (clone $base)
            ->selectRaw('report_schools.id as id, report_schools.name as name, COUNT(DISTINCT student_enrollments.id) as total, COUNT(DISTINCT CASE WHEN report_examinations.student_id IS NOT NULL THEN student_enrollments.id END) as checked')
            ->groupBy('report_schools.id', 'report_schools.name')
            ->orderBy('report_schools.name')
            ->get();
        $byClass = (clone $base)
            ->selectRaw('report_schools.id as school_id, report_schools.name as school_name, report_classes.id as id, report_classes.name as name, COUNT(DISTINCT student_enrollments.id) as total, COUNT(DISTINCT CASE WHEN report_examinations.student_id IS NOT NULL THEN student_enrollments.id END) as checked')
            ->groupBy('report_schools.id', 'report_schools.name', 'report_classes.id', 'report_classes.name')
            ->orderBy('report_schools.name')
            ->orderBy('report_classes.name')
            ->get();

        $visibleSchools = $bySchool->filter(fn ($row) => (int) $row->total >= self::SMALL_GROUP_THRESHOLD)
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                ...$this->coverageRow($row),
            ])->values();
        $visibleClasses = $byClass->filter(fn ($row) => (int) $row->total >= self::SMALL_GROUP_THRESHOLD)
            ->map(fn ($row) => [
                'school_id' => (int) $row->school_id,
                'school_name' => $row->school_name,
                'id' => $row->id === null ? null : (int) $row->id,
                'name' => $row->name ?? 'Kelas belum ditentukan',
                ...$this->coverageRow($row),
            ])->values();
        $audit->record($request, 'report.summary_viewed', 'AcademicYear', metadata: [
            'filters' => $filters,
            'total' => $total,
            'checked' => $checked,
            'suppressed_school_groups' => $bySchool->count() - $visibleSchools->count(),
            'suppressed_class_groups' => $byClass->count() - $visibleClasses->count(),
        ]);

        return response()->json([
            'period' => [
                'academic_year' => $filters['academic_year'],
                'semester' => $filters['semester'] ?? null,
            ],
            'summary' => $summary,
            'by_school' => $visibleSchools,
            'by_class' => $visibleClasses,
            'privacy' => [
                'minimum_group_size' => self::SMALL_GROUP_THRESHOLD,
                'suppressed_school_groups' => $bySchool->count() - $visibleSchools->count(),
                'suppressed_class_groups' => $byClass->count() - $visibleClasses->count(),
            ],
        ]);
    }

    public function exportExaminations(
        Request $request,
        ScopeAccess $scope,
        AuditLogger $audit,
        ExaminationIndicatorSchema $schema,
    ): StreamedResponse {
        $filters = $this->validatedFilters($request, true);
        $user = $request->user();
        $schools = $scope->schoolsFor($user);
        if (isset($filters['school_id'])) {
            abort_unless($schools->whereKey($filters['school_id'])->exists(), 403, 'Akses sekolah tidak diizinkan.');
        }
        $this->assertClassFilter($filters, $schools->select('id'));

        $query = Examination::query()
            ->with(['student:id,nisn,name,birth_date,sex', 'schoolClass:id,name', 'semester:id,academic_year_id,number', 'semester.academicYear:id,name', 'observations'])
            ->whereIn('school_id', $schools->select('id'))
            ->whereIn('status', ['finalized', 'published'])
            ->whereHas('semester.academicYear', fn (Builder $year) => $year->where('name', $filters['academic_year']));
        if (isset($filters['school_id'])) {
            $query->where('school_id', $filters['school_id']);
        }
        if (isset($filters['level'])) {
            $query->whereHas('school', fn (Builder $school) => $school->where('level', $filters['level']));
        }
        if (isset($filters['school_class_id'])) {
            $query->where('school_class_id', $filters['school_class_id']);
        }
        if (isset($filters['semester'])) {
            $query->whereHas('semester', fn (Builder $semester) => $semester->where('number', $filters['semester']));
        }
        if (isset($filters['status'])) {
            abort_if(
                ! $user->hasRole('puskesmas_operator') && ! $user->hasRole('system_admin') && $filters['status'] !== 'published',
                403,
                'Sekolah hanya dapat mengunduh hasil yang telah dipublikasikan.',
            );
            $query->where('status', $filters['status']);
        } elseif (! $user->hasRole('puskesmas_operator') && ! $user->hasRole('system_admin')) {
            $query->where('status', 'published');
        }

        $examinations = $query->orderBy('school_id')->orderBy('student_id')->orderBy('semester_id')
            ->limit(self::MAX_EXPORT_ROWS + 1)
            ->get();
        abort_if($examinations->count() > self::MAX_EXPORT_ROWS, 422, 'Ekspor melebihi 5.000 baris. Persempit filter sekolah, kelas, atau periode.');
        $audit->record($request, 'report.examinations_exported', Examination::class, metadata: [
            'filters' => $filters,
            'row_count' => $examinations->count(),
            'snapshot_at' => now()->toIso8601String(),
        ]);

        $filename = 'laos-pemeriksaan-'.$filters['academic_year'].(isset($filters['semester']) ? '-semester-'.$filters['semester'] : '-tahunan').'.csv';

        return response()->streamDownload(function () use ($examinations, $schema): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('Tidak dapat menyiapkan file ekspor.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            $headers = ['Nama siswa', 'NISN', 'Jenis kelamin', 'Sekolah', 'Kelas', 'Tahun ajaran', 'Semester', 'Tanggal pemeriksaan', 'Pemeriksa', 'Status', 'Versi', 'IMT'];
            foreach ($schema->keys() as $key) {
                $headers[] = $key;
            }
            fputcsv($output, array_map($this->spreadsheetSafe(...), $headers), ',', '"', '');

            foreach ($examinations as $examination) {
                $observations = $examination->observations->keyBy('indicator_key');
                $row = [
                    $examination->student->name,
                    $examination->student->nisn,
                    $examination->student->sex,
                    $examination->school_name_snapshot,
                    $examination->class_name_snapshot,
                    $examination->semester->academicYear->name,
                    'Semester '.$examination->semester->number,
                    $examination->examined_at->toDateString(),
                    $examination->examiner_name,
                    $examination->status,
                    $examination->version,
                    $examination->bmi,
                ];
                foreach ($schema->keys() as $key) {
                    $observation = $observations->get($key);
                    $value = ! $observation || $observation->status === 'not_examined'
                        ? 'Belum diperiksa'
                        : (is_scalar($observation->value) ? (string) $observation->value : json_encode($observation->value));
                    $row[] = $value;
                }
                fputcsv($output, array_map($this->spreadsheetSafe(...), $row), ',', '"', '');
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function validatedFilters(Request $request, bool $includeStatus = false): array
    {
        $rules = [
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['sometimes', 'integer', 'between:1,2'],
            'school_id' => ['sometimes', 'integer', 'exists:schools,id'],
            'school_class_id' => ['sometimes', 'integer', 'exists:school_classes,id'],
            'level' => ['sometimes', 'in:sd,smp,sma,slb'],
        ];
        if ($includeStatus) {
            $rules['status'] = ['sometimes', 'in:finalized,published'];
        }

        return $request->validate($rules);
    }

    private function assertClassFilter(array $filters, $schoolIds): void
    {
        if (! isset($filters['school_class_id'])) {
            return;
        }
        $class = SchoolClass::findOrFail($filters['school_class_id']);
        abort_unless($schoolIds->whereKey($class->school_id)->exists(), 403, 'Akses kelas tidak diizinkan.');
        abort_if(isset($filters['school_id']) && $class->school_id !== (int) $filters['school_id'], 422, 'Kelas bukan milik sekolah yang dipilih.');
    }

    private function coverageRow(object $row): array
    {
        $total = (int) $row->total;
        $checked = (int) $row->checked;

        return [
            'total' => $total,
            'checked' => $checked,
            'not_checked' => max(0, $total - $checked),
            'coverage_percent' => $total === 0 ? 0 : round(($checked / $total) * 100, 2),
        ];
    }

    private function spreadsheetSafe(mixed $value): string|int|float
    {
        if (! is_string($value)) {
            return $value ?? '';
        }
        $value = str_replace(["\0", "\r"], ['', ' '], $value);

        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
