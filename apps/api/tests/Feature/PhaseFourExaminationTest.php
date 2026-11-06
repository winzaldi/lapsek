<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\HealthCenter;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAccountLink;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\ExaminationIndicatorSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFourExaminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exam_creation_checks_enrollment_and_calculates_bmi_without_assigning_nutrition_category(): void
    {
        $this->seed();
        $context = $this->createContext();
        $response = $this->actingAs($context['operator'], 'sanctum')->postJson('/api/v1/examinations', [
            'student_id' => $context['student']->id,
            'semester_id' => $context['semester']->id,
            'examined_at' => '2025-09-15',
            'examiner_name' => 'Petugas UKS',
            'observations' => [
                'height_cm' => ['status' => 'recorded', 'value' => 150],
                'weight_kg' => ['status' => 'recorded', 'value' => 50],
                'nutrition_status' => ['status' => 'not_examined'],
                'breakfast' => ['status' => 'recorded', 'value' => false],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.bmi', '22.22')
            ->assertJsonPath('data.bmi_calculation_version', 'bmi-kg-m2-v1')
            ->assertJsonPath('data.observations.nutrition_status.status', 'not_examined')
            ->assertJsonPath('data.observations.breakfast.value', false);

        $this->assertDatabaseHas('examinations', [
            'id' => $response->json('data.id'),
            'school_id' => $context['school']->id,
            'school_class_id' => $context['class']->id,
            'status' => 'draft',
        ]);
    }

    public function test_exam_creation_rejects_wrong_period_and_duplicate_student_semester(): void
    {
        $this->seed();
        $context = $this->createContext();
        $body = [
            'student_id' => $context['student']->id,
            'semester_id' => $context['semester']->id,
            'examined_at' => '2026-07-01',
            'examiner_name' => 'Petugas UKS',
        ];

        $this->actingAs($context['operator'], 'sanctum')
            ->postJson('/api/v1/examinations', $body)
            ->assertUnprocessable();
        $body['examined_at'] = '2025-09-15';
        $this->postJson('/api/v1/examinations', $body)->assertCreated();
        $this->postJson('/api/v1/examinations', $body)->assertStatus(409);
    }

    public function test_puskesmas_operator_cannot_create_an_examination_outside_assigned_health_center(): void
    {
        $this->seed();
        $context = $this->createContext();
        $otherCenter = HealthCenter::create(['code' => 'PKM-OTHER', 'name' => 'Puskesmas Lain']);
        $otherSchool = School::create([
            'health_center_id' => $otherCenter->id,
            'npsn' => '20000002',
            'name' => 'Sekolah Lain',
            'level' => 'smp',
        ]);
        $otherYear = AcademicYear::create([
            'school_id' => $otherSchool->id,
            'name' => '2025-2026',
            'starts_on' => '2025-07-01',
            'ends_on' => '2026-06-30',
            'is_active' => true,
        ]);
        $otherSemester = Semester::create([
            'academic_year_id' => $otherYear->id,
            'number' => 1,
            'starts_on' => '2025-07-01',
            'ends_on' => '2025-12-31',
        ]);
        $otherStudent = Student::create(['nisn' => '2000000002', 'name' => 'Siswa Sekolah Lain']);
        StudentEnrollment::create([
            'student_id' => $otherStudent->id,
            'school_id' => $otherSchool->id,
            'academic_year_id' => $otherYear->id,
            'starts_on' => '2025-07-01',
        ]);

        $this->actingAs($context['operator'], 'sanctum')
            ->postJson('/api/v1/examinations', [
                'student_id' => $otherStudent->id,
                'semester_id' => $otherSemester->id,
                'examined_at' => '2025-09-10',
                'examiner_name' => 'Petugas UKS',
            ])
            ->assertForbidden();
        $this->assertDatabaseMissing('examinations', [
            'student_id' => $otherStudent->id,
            'semester_id' => $otherSemester->id,
        ]);
    }

    public function test_puskesmas_coverage_lists_checked_and_not_checked_students_without_exposing_results(): void
    {
        $this->seed();
        $context = $this->createContext();
        $this->createDraft($context);
        $waitingStudent = Student::create(['nisn' => '2000000002', 'name' => 'Siswa Belum Diperiksa']);
        StudentEnrollment::create([
            'student_id' => $waitingStudent->id,
            'school_id' => $context['school']->id,
            'school_class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'starts_on' => '2025-07-01',
        ]);

        $this->actingAs($context['operator'], 'sanctum')
            ->getJson('/api/v1/examination-coverage?semester_id='.$context['semester']->id)
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.checked', 1)
            ->assertJsonPath('summary.not_checked', 1)
            ->assertJsonPath('summary.coverage_percent', 50)
            ->assertJsonPath('data.data.0.examination_status', 'draft')
            ->assertJsonPath('data.data.1.coverage_status', 'not_checked')
            ->assertJsonMissingPath('data.data.0.observations');
    }

    public function test_finalization_requires_explicit_state_for_each_indicator_and_creates_version_snapshot(): void
    {
        $this->seed();
        $context = $this->createContext();
        $examination = $this->createDraft($context);
        $this->actingAs($context['operator'], 'sanctum')
            ->postJson("/api/v1/examinations/{$examination->id}/finalize")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['observations', 'missing_indicators']);

        $this->patchObservations($context['operator'], $examination, $this->completeObservations());
        $this->postJson("/api/v1/examinations/{$examination->id}/finalize")
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.version', 1);
        $this->assertDatabaseHas('examination_revisions', [
            'examination_id' => $examination->id,
            'version' => 1,
            'reason' => null,
        ]);
    }

    public function test_correction_preserves_old_version_and_requires_current_version_and_reason(): void
    {
        $this->seed();
        $context = $this->createContext();
        $examination = $this->createDraft($context);
        $this->patchObservations($context['operator'], $examination, $this->completeObservations());
        $this->actingAs($context['operator'], 'sanctum')
            ->postJson("/api/v1/examinations/{$examination->id}/finalize")
            ->assertOk();

        $this->postJson("/api/v1/examinations/{$examination->id}/corrections", [
            'reason' => 'Perbaikan hasil wawancara',
            'expected_version' => 99,
            'observations' => ['breakfast' => ['status' => 'recorded', 'value' => true]],
        ])->assertStatus(409);

        $this->postJson("/api/v1/examinations/{$examination->id}/corrections", [
            'reason' => 'Perbaikan hasil wawancara',
            'expected_version' => 1,
            'observations' => ['breakfast' => ['status' => 'recorded', 'value' => true]],
        ])->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.observations.breakfast.value', true);

        $revisions = $this->actingAs($context['operator'], 'sanctum')
            ->getJson("/api/v1/examinations/{$examination->id}/revisions")
            ->assertOk();
        $this->assertSame(2, count($revisions->json('data')));
        $this->assertSame(false, $revisions->json('data.0.snapshot.observations.breakfast.value'));
        $this->assertSame('Perbaikan hasil wawancara', $revisions->json('data.1.reason'));
        $this->assertSame(true, $revisions->json('data.1.snapshot.observations.breakfast.value'));
    }

    public function test_school_and_student_can_only_read_published_examinations_in_their_scope(): void
    {
        $this->seed();
        $context = $this->createContext();
        $examination = $this->createDraft($context);
        $schoolOperator = $this->createScopedUser('school_operator', 'school', $context['school']->id);
        $studentUser = User::factory()->create();
        $studentUser->roles()->attach(Role::where('name', 'student')->firstOrFail()->id);
        StudentAccountLink::create([
            'student_id' => $context['student']->id,
            'user_id' => $studentUser->id,
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $this->actingAs($studentUser, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('student_id', $context['student']->id);
        $otherStudent = Student::create(['nisn' => '2000000002', 'name' => 'Siswa Lain']);
        StudentEnrollment::create([
            'student_id' => $otherStudent->id,
            'school_id' => $context['school']->id,
            'school_class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'starts_on' => '2025-07-01',
        ]);

        $this->actingAs($schoolOperator, 'sanctum')
            ->getJson("/api/v1/examinations/{$examination->id}")
            ->assertNotFound();
        $this->actingAs($studentUser, 'sanctum')
            ->getJson("/api/v1/students/{$context['student']->id}/health-history")
            ->assertOk()
            ->assertJsonCount(0, 'data.data');

        $this->actingAs($context['operator'], 'sanctum')
            ->patchJson("/api/v1/examinations/{$examination->id}", [
                'observations' => $this->completeObservations(),
            ])->assertOk();
        $this->postJson("/api/v1/examinations/{$examination->id}/finalize")->assertOk();
        $this->postJson("/api/v1/examinations/{$examination->id}/publish")->assertOk();

        $context['school']->update(['name' => 'Nama Sekolah Baru']);
        $context['class']->update(['name' => '7B']);
        $this->actingAs($schoolOperator, 'sanctum')
            ->getJson("/api/v1/examinations/{$examination->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.school.name', 'Sekolah Uji')
            ->assertJsonPath('data.school_class.name', '7A');
        $this->actingAs($studentUser, 'sanctum')
            ->getJson("/api/v1/students/{$context['student']->id}/health-history")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('available_periods.0.academic_year', '2025-2026')
            ->assertJsonPath('available_periods.0.semester', 1);
        $this->getJson("/api/v1/students/{$context['student']->id}/health-history?academic_year=2025-2026&semester=1")
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
        $this->getJson("/api/v1/students/{$otherStudent->id}/health-history")->assertNotFound();
    }

    public function test_student_session_does_not_expose_a_pending_account_link(): void
    {
        $this->seed();
        $context = $this->createContext();
        $studentUser = User::factory()->create();
        $studentUser->roles()->attach(Role::where('name', 'student')->firstOrFail()->id);
        StudentAccountLink::create([
            'student_id' => $context['student']->id,
            'user_id' => $studentUser->id,
            'status' => 'pending',
        ]);

        $this->actingAs($studentUser, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('student_id', null);
        $this->getJson("/api/v1/students/{$context['student']->id}/health-history")
            ->assertNotFound();
    }

    public function test_unexamined_indicator_cannot_have_a_value_and_clinical_values_are_constrained_to_source_options(): void
    {
        $this->seed();
        $context = $this->createContext();
        $this->actingAs($context['operator'], 'sanctum')
            ->postJson('/api/v1/examinations', [
                'student_id' => $context['student']->id,
                'semester_id' => $context['semester']->id,
                'examined_at' => '2026-01-15',
                'examiner_name' => 'Petugas UKS',
                'observations' => [
                    'breakfast' => ['status' => 'not_examined', 'value' => false],
                    'personal_hygiene' => ['status' => 'recorded', 'value' => 'unknown'],
                ],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['observations.breakfast.value', 'observations.personal_hygiene.value']);
    }

    private function createContext(): array
    {
        $center = HealthCenter::create(['code' => 'PKM-TST', 'name' => 'Puskesmas Uji']);
        $school = School::create([
            'health_center_id' => $center->id,
            'npsn' => '20000001',
            'name' => 'Sekolah Uji',
            'level' => 'smp',
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
            'name' => '7A',
            'grade' => 7,
            'academic_level' => 'smp',
        ]);
        $student = Student::create(['nisn' => '2000000001', 'name' => 'Siswa Uji']);
        StudentEnrollment::create([
            'student_id' => $student->id,
            'school_id' => $school->id,
            'school_class_id' => $class->id,
            'academic_year_id' => $year->id,
            'starts_on' => '2025-07-01',
        ]);
        $operator = $this->createScopedUser('puskesmas_operator', 'puskesmas', $center->id);

        return compact('center', 'school', 'year', 'semester', 'class', 'student', 'operator');
    }

    private function createScopedUser(string $roleName, string $scopeType, int $scopeId): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail()->id, [
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);

        return $user;
    }

    private function createDraft(array $context): Examination
    {
        return Examination::create([
            'student_id' => $context['student']->id,
            'school_id' => $context['school']->id,
            'school_class_id' => $context['class']->id,
            'semester_id' => $context['semester']->id,
            'school_name_snapshot' => $context['school']->name,
            'class_name_snapshot' => $context['class']->name,
            'examined_at' => '2025-09-10',
            'examiner_name' => 'Petugas UKS',
            'entered_by_user_id' => $context['operator']->id,
            'status' => 'draft',
        ]);
    }

    private function patchObservations(User $operator, Examination $examination, array $observations): void
    {
        $this->actingAs($operator, 'sanctum')
            ->patchJson("/api/v1/examinations/{$examination->id}", ['observations' => $observations])
            ->assertOk();
    }

    private function completeObservations(): array
    {
        $observations = [];
        foreach (app(ExaminationIndicatorSchema::class)->keys() as $key) {
            $observations[$key] = ['status' => 'not_examined'];
        }
        $observations['height_cm'] = ['status' => 'recorded', 'value' => 150];
        $observations['weight_kg'] = ['status' => 'recorded', 'value' => 50];
        $observations['nutrition_status'] = ['status' => 'recorded', 'value' => 'normal'];
        $observations['breakfast'] = ['status' => 'recorded', 'value' => false];

        return $observations;
    }
}
