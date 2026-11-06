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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentImportController extends Controller
{
    private const MAX_ROWS = 1000;

    public function preview(Request $request, ScopeAccess $scope): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'csv' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ]);
        [$school, $year] = $this->resolveTarget($data, $request, $scope);
        $rows = $this->parseAndValidate($request->file('csv'), $school, $year);
        $errors = collect($rows)->sum(fn ($row) => count($row['errors']));
        $token = $this->makeTicket($request, $school, $year, $request->file('csv'));

        return response()->json([
            'preview_token' => $token,
            'expires_in' => 600,
            'summary' => [
                'rows' => count($rows),
                'valid_rows' => count($rows) - collect($rows)->filter(fn ($row) => $row['errors'] !== [])->count(),
                'rows_with_errors' => collect($rows)->filter(fn ($row) => $row['errors'] !== [])->count(),
                'field_errors' => $errors,
            ],
            'data' => $rows,
        ]);
    }

    public function commit(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'preview_token' => ['required', 'string', 'max:4096'],
            'csv' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ]);
        [$school, $year] = $this->resolveTarget($data, $request, $scope);
        $this->verifyTicket($data['preview_token'], $request, $school, $year, $request->file('csv'));
        $rows = $this->parseAndValidate($request->file('csv'), $school, $year);
        $invalidRows = collect($rows)->filter(fn ($row) => $row['errors'] !== [])->values();
        if ($invalidRows->isNotEmpty()) {
            return response()->json([
                'message' => 'Impor dibatalkan karena terdapat baris tidak valid. Tidak ada data yang disimpan.',
                'data' => $invalidRows,
            ], 422);
        }

        $createdCount = DB::transaction(function () use ($rows, $school, $year): int {
            foreach ($rows as $row) {
                $student = Student::where('nisn', $row['nisn'])->lockForUpdate()->first();
                if ($student && $student->enrollments()->whereNull('ends_on')->exists()) {
                    throw ValidationException::withMessages([
                        'csv' => ["NISN {$row['nisn']} telah memiliki enrollment aktif; impor dibatalkan."],
                    ]);
                }
                $student ??= Student::create([
                    'nisn' => $row['nisn'],
                    'name' => $row['name'],
                    'birth_date' => $row['birth_date'] ?: null,
                    'sex' => $row['sex'] ?: null,
                ]);
                $class = SchoolClass::where('school_id', $school->id)->where('name', $row['class_name'])->firstOrFail();
                StudentEnrollment::create([
                    'student_id' => $student->id,
                    'school_id' => $school->id,
                    'school_class_id' => $class->id,
                    'academic_year_id' => $year->id,
                    'starts_on' => $year->starts_on,
                ]);
            }

            return count($rows);
        });
        $audit->record($request, 'students.imported', 'Student', metadata: [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'count' => $createdCount,
        ]);

        return response()->json(['message' => 'Impor berhasil.', 'imported_count' => $createdCount], 201);
    }

    private function resolveTarget(array $data, Request $request, ScopeAccess $scope): array
    {
        $school = School::findOrFail($data['school_id']);
        $scope->assertSchool($request->user(), $school);
        $year = AcademicYear::findOrFail($data['academic_year_id']);
        abort_unless($year->school_id === $school->id, 422, 'Tahun ajaran bukan milik sekolah tersebut.');

        return [$school, $year];
    }

    private function parseAndValidate(UploadedFile $file, School $school, AcademicYear $year): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['csv' => ['File CSV tidak dapat dibaca.']]);
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '\\');
            $requiredHeaders = ['nisn', 'name', 'birth_date', 'sex', 'class_name'];
            $header = is_array($header)
                ? array_map(fn ($value) => mb_strtolower(trim((string) $value)), $header)
                : [];
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if (count($header) !== count(array_unique($header)) || array_diff($requiredHeaders, $header) !== []) {
                throw ValidationException::withMessages([
                    'csv' => ['Header wajib: nisn,name,birth_date,sex,class_name.'],
                ]);
            }

            $rows = [];
            $seenNisn = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $line++;
                if ($values === [null] || $values === []) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['csv' => ['Maksimal 1.000 baris per impor.']]);
                }
                if (count($values) !== count($header)) {
                    $rows[] = ['line' => $line, 'errors' => ['Jumlah kolom tidak sesuai header.']];

                    continue;
                }

                $record = array_combine($header, array_map(fn ($value) => trim((string) $value), $values));
                foreach (['birth_date', 'sex'] as $optionalField) {
                    if (($record[$optionalField] ?? '') === '') {
                        $record[$optionalField] = null;
                    }
                }
                $rowValidator = Validator::make($record, [
                    'nisn' => ['required', 'string', 'max:20'],
                    'name' => ['required', 'string', 'max:255'],
                    'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                    'sex' => ['nullable', 'string', 'max:16'],
                    'class_name' => ['required', 'string', 'max:64'],
                ]);
                $errors = $rowValidator->errors()->all();
                $nisn = $record['nisn'] ?? '';
                if ($nisn !== '' && isset($seenNisn[$nisn])) {
                    $errors[] = 'NISN duplikat pada baris '.$seenNisn[$nisn].'.';
                }
                if ($nisn !== '') {
                    $seenNisn[$nisn] = $line;
                    $existing = Student::where('nisn', $nisn)->first();
                    if ($existing?->enrollments()->whereNull('ends_on')->exists()) {
                        $errors[] = 'Siswa sudah memiliki enrollment aktif.';
                    }
                    if ($existing && isset($record['name'])
                        && mb_strtolower($existing->name) !== mb_strtolower($record['name'])) {
                        $errors[] = 'Nama berbeda dari profil dengan NISN yang sama; perlu diperiksa manual.';
                    }
                    if ($existing && ! empty($record['birth_date']) && $existing->birth_date?->toDateString() !== $record['birth_date']) {
                        $errors[] = 'Tanggal lahir berbeda dari profil dengan NISN yang sama; perlu diperiksa manual.';
                    }
                }
                if (! empty($record['class_name']) && ! SchoolClass::where('school_id', $school->id)
                    ->where('name', $record['class_name'])->where('is_active', true)->exists()) {
                    $errors[] = 'Kelas tidak ditemukan atau tidak aktif pada sekolah ini.';
                }
                $rows[] = [
                    'line' => $line,
                    'nisn' => $nisn,
                    'name' => $record['name'] ?? '',
                    'birth_date' => $record['birth_date'] ?? '',
                    'sex' => $record['sex'] ?? '',
                    'class_name' => $record['class_name'] ?? '',
                    'errors' => array_values(array_unique($errors)),
                ];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['csv' => ['File tidak memiliki baris data.']]);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function makeTicket(Request $request, School $school, AcademicYear $year, UploadedFile $file): string
    {
        $payload = json_encode([
            'user' => $request->user()->id,
            'school' => $school->id,
            'year' => $year->id,
            'file' => hash_file('sha256', $file->getRealPath()),
            'expires' => now()->addMinutes(10)->timestamp,
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, (string) config('app.key'), true);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=').'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function verifyTicket(string $ticket, Request $request, School $school, AcademicYear $year, UploadedFile $file): void
    {
        $parts = explode('.', $ticket);
        if (count($parts) !== 2) {
            abort(422, 'Token pratinjau impor tidak valid.');
        }
        $decode = fn (string $value) => base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        $payload = $decode($parts[0]);
        $signature = $decode($parts[1]);
        if ($payload === false || $signature === false
            || ! hash_equals(hash_hmac('sha256', $payload, (string) config('app.key'), true), $signature)) {
            abort(422, 'Token pratinjau impor tidak valid.');
        }

        $claims = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(
            ($claims['user'] ?? null) === $request->user()->id
                && ($claims['school'] ?? null) === $school->id
                && ($claims['year'] ?? null) === $year->id
                && ($claims['expires'] ?? 0) >= now()->timestamp
                && is_string($claims['file'] ?? null)
                && hash_equals($claims['file'], (string) hash_file('sha256', $file->getRealPath())),
            422,
            'Token pratinjau kedaluwarsa atau tidak sesuai dengan file dan cakupan.',
        );
    }
}
