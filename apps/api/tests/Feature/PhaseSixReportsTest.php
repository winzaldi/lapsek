<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportExport;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\HealthCenter;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PhaseSixReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_completed_examinations_and_suppresses_small_groups(): void
    {
        $this->seed();
        $context = $this->createContext();
        for ($index = 1; $index <= 4; $index++) {
            $status = match ($index) {
                1, 2 => 'published',
                3 => 'finalized',
                default => 'draft',
            };
            $this->createStudent($context, 'Siswa '.$index, $status);
        }

        $this->actingAs($context['operator'], 'sanctum')
            ->getJson('/api/v1/reports/summary?academic_year=2025-2026&semester=1&school_id='.$context['school']->id)
            ->assertOk()
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.checked', 2)
            ->assertJsonPath('summary.not_checked', 2)
            ->assertJsonPath('summary.coverage_percent', 50)
            ->assertJsonCount(0, 'by_school')
            ->assertJsonCount(0, 'by_class')
            ->assertJsonPath('privacy.minimum_group_size', 5)
            ->assertJsonPath('privacy.suppressed_class_groups', 1);

        $puskesmasOperator = User::factory()->create();
        $puskesmasOperator->roles()->attach(Role::where('name', 'puskesmas_operator')->firstOrFail()->id, [
            'scope_type' => 'puskesmas',
            'scope_id' => $context['center']->id,
        ]);
        $this->actingAs($puskesmasOperator, 'sanctum')
            ->getJson('/api/v1/reports/summary?academic_year=2025-2026&school_id='.$context['school']->id)
            ->assertOk()
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.checked', 3)
            ->assertJsonPath('summary.not_checked', 1);
    }

    public function test_summary_rejects_school_outside_the_callers_scope(): void
    {
        $this->seed();
        $context = $this->createContext();
        $otherSchool = $this->createSchool('OTHER');

        $this->actingAs($context['operator'], 'sanctum')
            ->getJson('/api/v1/reports/summary?academic_year=2025-2026&school_id='.$otherSchool->id)
            ->assertForbidden();
    }

    public function test_summary_includes_school_and_class_chart_labels_for_groups_over_the_threshold(): void
    {
        $this->seed();
        $context = $this->createContext();
        for ($index = 1; $index <= 5; $index++) {
            $this->createStudent($context, 'Siswa '.$index, 'published');
        }

        $this->actingAs($context['operator'], 'sanctum')
            ->getJson('/api/v1/reports/summary?academic_year=2025-2026&semester=1')
            ->assertOk()
            ->assertJsonPath('summary.total', 5)
            ->assertJsonPath('summary.checked', 5)
            ->assertJsonPath('by_school.0.id', $context['school']->id)
            ->assertJsonPath('by_school.0.name', $context['school']->name)
            ->assertJsonPath('by_class.0.id', $context['class']->id)
            ->assertJsonPath('by_class.0.name', $context['class']->name);
    }

    public function test_school_csv_contains_only_published_rows_and_neutralizes_spreadsheet_formulas(): void
    {
        $this->seed();
        $context = $this->createContext();
        $this->createStudent($context, '=1+1', 'published');
        $this->createStudent($context, 'Hasil Final', 'finalized');

        $response = $this->actingAs($context['operator'], 'sanctum')
            ->get('/api/v1/reports/examinations.csv?academic_year=2025-2026&school_id='.$context['school']->id);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString('Hasil Final', $csv);
        $this->assertSame(2, substr_count($csv, "\n"));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $context['operator']->id,
            'action' => 'report.examinations_exported',
        ]);
    }

    public function test_export_jobs_generate_private_csv_xlsx_and_pdf_and_allow_scoped_redownloads(): void
    {
        $this->seed();
        Storage::fake('local');
        $context = $this->createContext();
        $this->createStudent($context, '=1+1', 'published');

        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $response = $this->actingAs($context['operator'], 'sanctum')
                ->postJson('/api/v1/reports/exports', [
                    'academic_year' => '2025-2026',
                    'school_id' => $context['school']->id,
                    'format' => $format,
                    'status' => 'published',
                ]);
            $response->assertStatus(202)
                ->assertJsonPath('data.status', 'completed')
                ->assertJsonPath('data.format', $format)
                ->assertJsonPath('data.row_count', 1)
                ->assertJsonPath('data.can_download', true);

            $exportId = $response->json('data.id');
            $path = "report-exports/{$exportId}.{$format}";
            Storage::disk('local')->assertExists($path);
            $contents = Storage::disk('local')->get($path);
            if ($format === 'csv') {
                $this->assertStringContainsString("\xEF\xBB\xBF", $contents);
                $this->assertStringContainsString("'=1+1", $contents);
            } elseif ($format === 'xlsx') {
                $this->assertSame('PK', substr($contents, 0, 2));
                $temporaryPath = tempnam(sys_get_temp_dir(), 'laos-test-xlsx-');
                $this->assertNotFalse($temporaryPath);
                try {
                    file_put_contents($temporaryPath, $contents);
                    $sheet = IOFactory::load($temporaryPath)->getActiveSheet();
                    $this->assertSame("'=1+1", $sheet->getCell('A2')->getValue());
                    $this->assertSame('s', $sheet->getCell('A2')->getDataType());
                } finally {
                    unlink($temporaryPath);
                }
            } else {
                $this->assertStringStartsWith('%PDF-', $contents);
            }

            $this->actingAs($context['operator'], 'sanctum')
                ->get("/api/v1/reports/exports/{$exportId}/download")
                ->assertOk()
                ->assertHeader('cache-control', 'no-store, private');
            $this->assertDatabaseHas('audit_logs', [
                'actor_id' => $context['operator']->id,
                'action' => 'report.export_downloaded',
            ]);
        }
    }

    public function test_export_jobs_are_queued_and_history_is_shared_only_with_users_in_scope(): void
    {
        $this->seed();
        Storage::fake('local');
        $context = $this->createContext();
        $this->createStudent($context, 'Siswa Uji', 'published');
        Queue::fake();

        $response = $this->actingAs($context['operator'], 'sanctum')
            ->postJson('/api/v1/reports/exports', [
                'academic_year' => '2025-2026',
                'school_id' => $context['school']->id,
                'format' => 'csv',
                'status' => 'published',
            ])->assertStatus(202)->assertJsonPath('data.status', 'queued');
        Queue::assertPushedOn('report-exports', GenerateReportExport::class);
        $exportId = $response->json('data.id');

        $colleague = User::factory()->create();
        $colleague->roles()->attach(Role::where('name', 'school_operator')->firstOrFail()->id, [
            'scope_type' => 'school',
            'scope_id' => $context['school']->id,
        ]);
        $this->actingAs($colleague, 'sanctum')
            ->getJson('/api/v1/reports/exports')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $exportId);

        $otherSchool = $this->createSchool('OTHER');
        $otherOperator = User::factory()->create();
        $otherOperator->roles()->attach(Role::where('name', 'school_operator')->firstOrFail()->id, [
            'scope_type' => 'school',
            'scope_id' => $otherSchool->id,
        ]);
        $this->actingAs($otherOperator, 'sanctum')
            ->getJson("/api/v1/reports/exports/{$exportId}")
            ->assertForbidden();
        $this->get("/api/v1/reports/exports/{$exportId}/download")
            ->assertForbidden();
    }

    public function test_puskesmas_export_archive_is_available_to_replacement_operator_in_the_same_scope(): void
    {
        $this->seed();
        Storage::fake('local');
        $context = $this->createContext();
        $this->createStudent($context, 'Hasil Final', 'finalized');
        $operator = User::factory()->create();
        $operator->roles()->attach(Role::where('name', 'puskesmas_operator')->firstOrFail()->id, [
            'scope_type' => 'puskesmas',
            'scope_id' => $context['center']->id,
        ]);

        $response = $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/reports/exports', [
                'academic_year' => '2025-2026',
                'school_id' => $context['school']->id,
                'format' => 'csv',
            ])->assertStatus(202)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.row_count', 1);
        $exportId = $response->json('data.id');

        $this->actingAs($context['operator'], 'sanctum')
            ->getJson("/api/v1/reports/exports/{$exportId}")
            ->assertForbidden();

        $replacement = User::factory()->create();
        $replacement->roles()->attach(Role::where('name', 'puskesmas_operator')->firstOrFail()->id, [
            'scope_type' => 'puskesmas',
            'scope_id' => $context['center']->id,
        ]);
        $this->actingAs($replacement, 'sanctum');
        $this->getJson('/api/v1/reports/exports')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $exportId);
        $this->get("/api/v1/reports/exports/{$exportId}/download")
            ->assertOk();
    }

    public function test_export_request_rejects_finalized_records_for_school_users_and_purges_expired_files(): void
    {
        $this->seed();
        Storage::fake('local');
        $context = $this->createContext();
        $this->createStudent($context, 'Siswa Uji', 'published');

        $this->actingAs($context['operator'], 'sanctum')
            ->postJson('/api/v1/reports/exports', [
                'academic_year' => '2025-2026',
                'school_id' => $context['school']->id,
                'format' => 'csv',
                'status' => 'finalized',
            ])->assertForbidden();

        $response = $this->actingAs($context['operator'], 'sanctum')
            ->postJson('/api/v1/reports/exports', [
                'academic_year' => '2025-2026',
                'school_id' => $context['school']->id,
                'format' => 'csv',
                'status' => 'published',
            ])->assertStatus(202);
        $exportId = $response->json('data.id');
        $path = "report-exports/{$exportId}.csv";

        $this->travel(8)->days();
        $this->artisan('reports:purge-expired-exports')->assertSuccessful();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseHas('report_exports', ['id' => $exportId, 'status' => 'expired', 'path' => null]);
    }

    private function createContext(): array
    {
        $center = HealthCenter::create(['code' => 'PKM-REPORT', 'name' => 'Puskesmas Laporan']);
        $school = School::create([
            'health_center_id' => $center->id,
            'npsn' => '90000001',
            'name' => 'Sekolah Laporan',
            'level' => 'sd',
        ]);
        $year = AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2025-2026',
            'starts_on' => '2025-07-01',
            'ends_on' => '2026-06-30',
            'is_active' => true,
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'number' => 1,
            'starts_on' => '2025-07-01',
            'ends_on' => '2025-12-31',
        ]);
        $class = SchoolClass::create([
            'school_id' => $school->id,
            'name' => 'Kelas 1',
            'grade' => 1,
            'academic_level' => 'sd',
            'is_active' => true,
        ]);
        $operator = User::factory()->create();
        $operator->roles()->attach(Role::where('name', 'school_operator')->firstOrFail()->id, [
            'scope_type' => 'school',
            'scope_id' => $school->id,
        ]);

        return compact('center', 'school', 'year', 'semester', 'class', 'operator');
    }

    private function createSchool(string $suffix): School
    {
        $center = HealthCenter::create(['code' => 'PKM-'.$suffix, 'name' => 'Puskesmas '.$suffix]);

        return School::create([
            'health_center_id' => $center->id,
            'npsn' => '90000002',
            'name' => 'Sekolah '.$suffix,
            'level' => 'sd',
        ]);
    }

    private function createStudent(array $context, string $name, string $status): void
    {
        $student = Student::create(['name' => $name]);
        StudentEnrollment::create([
            'student_id' => $student->id,
            'school_id' => $context['school']->id,
            'school_class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'starts_on' => '2025-07-01',
        ]);
        Examination::create([
            'student_id' => $student->id,
            'school_id' => $context['school']->id,
            'school_class_id' => $context['class']->id,
            'semester_id' => $context['semester']->id,
            'school_name_snapshot' => $context['school']->name,
            'class_name_snapshot' => $context['class']->name,
            'examined_at' => '2025-09-10',
            'examiner_name' => 'Petugas UKS',
            'entered_by_user_id' => $context['operator']->id,
            'status' => $status,
            'version' => $status === 'draft' ? 0 : 1,
            'published_at' => $status === 'published' ? now() : null,
            'finalized_at' => $status === 'draft' ? null : now(),
        ]);
    }
}
