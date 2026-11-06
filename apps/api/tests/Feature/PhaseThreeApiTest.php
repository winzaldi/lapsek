<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\HealthCenter;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseThreeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_limited_lived_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Valid-password-123')]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Valid-password-123',
            'device_name' => 'test-device',
        ])->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('expires_in', 28800);

        $token = $login->json('access_token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_inactive_accounts_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Valid-password-123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Valid-password-123',
            'device_name' => 'test-device',
        ])->assertUnprocessable();
    }

    public function test_login_is_rate_limited_after_five_attempts_per_email_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'unknown@example.test',
                'password' => 'Invalid-password',
                'device_name' => 'test-device',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.test',
            'password' => 'Invalid-password',
            'device_name' => 'test-device',
        ])->assertTooManyRequests();
    }

    public function test_admin_offboarding_deactivates_account_and_revokes_all_api_tokens(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->firstOrFail()->id);
        $operator = User::factory()->create();
        $operator->roles()->attach(Role::where('name', 'puskesmas_operator')->firstOrFail()->id, [
            'scope_type' => 'puskesmas',
            'scope_id' => 1,
        ]);
        $token = $operator->createToken('operator-device')->plainTextToken;

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/users/{$operator->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $operator->id]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'user.status_changed',
            'resource_id' => $operator->id,
        ]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_can_link_and_verify_a_student_account_without_duplicate_student_role(): void
    {
        $this->seed();
        [, $school] = $this->createSchool('A');
        $year = AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2025-2026',
            'starts_on' => '2025-07-01',
            'ends_on' => '2026-06-30',
            'is_active' => true,
        ]);
        $student = Student::create(['nisn' => '1234567890', 'name' => 'Siswa Uji']);
        StudentEnrollment::create([
            'student_id' => $student->id,
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'starts_on' => '2025-07-01',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->firstOrFail()->id);
        $studentAccount = User::factory()->create();
        $studentAccount->roles()->attach(Role::where('name', 'student')->firstOrFail()->id);

        $link = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/student-account-links', [
                'student_id' => $student->id,
                'user_id' => $studentAccount->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->postJson('/api/v1/student-account-links/'.$link->json('data.id').'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->assertSame(1, $studentAccount->roles()->count());
        $this->assertDatabaseHas('student_account_links', [
            'student_id' => $student->id,
            'user_id' => $studentAccount->id,
            'status' => 'verified',
        ]);
    }

    public function test_admin_can_create_an_operator_account_with_assigned_school_scope(): void
    {
        $this->seed();
        [, $school] = $this->createSchool('A');
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->firstOrFail()->id);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'Operator Sekolah',
                'email' => 'operator.sekolah@example.test',
                'password' => 'Initial-password-123',
                'password_confirmation' => 'Initial-password-123',
                'role' => 'school_operator',
                'scope_id' => $school->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.roles.0.name', 'school_operator');

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $response->json('data.id'),
            'role_id' => Role::where('name', 'school_operator')->value('id'),
            'scope_type' => 'school',
            'scope_id' => $school->id,
        ]);
    }

    public function test_school_operator_only_sees_and_changes_data_in_assigned_school(): void
    {
        $this->seed();
        [$center, $assignedSchool] = $this->createSchool('A');
        [, $otherSchool] = $this->createSchool('B');
        $operator = User::factory()->create();
        $operator->roles()->attach(Role::where('name', 'school_operator')->firstOrFail()->id, [
            'scope_type' => 'school',
            'scope_id' => $assignedSchool->id,
        ]);
        $this->assertTrue($operator->hasPermission('schools.view'));
        $this->assertTrue($operator->is_active);

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/schools')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $assignedSchool->id);

        $assignedClass = SchoolClass::create([
            'school_id' => $assignedSchool->id,
            'name' => 'Kelas 1',
            'grade' => 1,
            'academic_level' => $assignedSchool->level,
            'is_active' => true,
        ]);
        $this->actingAs($operator, 'sanctum')
            ->patchJson('/api/v1/classes/'.$assignedClass->id, ['name' => 'Kelas 1-A'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Kelas 1-A');

        $otherClass = SchoolClass::create([
            'school_id' => $otherSchool->id,
            'name' => 'Kelas 2',
            'grade' => 2,
            'academic_level' => $otherSchool->level,
            'is_active' => true,
        ]);
        $this->actingAs($operator, 'sanctum')
            ->patchJson('/api/v1/classes/'.$otherClass->id, ['name' => 'Tidak boleh'])
            ->assertForbidden();
        $this->assertDatabaseHas('school_classes', ['id' => $otherClass->id, 'name' => 'Kelas 2']);

        $year = AcademicYear::create([
            'school_id' => $otherSchool->id,
            'name' => '2025-2026',
            'starts_on' => '2025-07-01',
            'ends_on' => '2026-06-30',
            'is_active' => true,
        ]);
        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/students', [
                'school_id' => $otherSchool->id,
                'academic_year_id' => $year->id,
                'name' => 'Siswa Uji',
                'starts_on' => '2025-07-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('students', ['name' => 'Siswa Uji']);
    }

    public function test_puskesmas_operator_scope_includes_only_its_assigned_schools(): void
    {
        $this->seed();
        [$center, $assignedSchool] = $this->createSchool('A');
        $otherCenter = HealthCenter::create(['code' => 'PKM-B', 'name' => 'Puskesmas B']);
        $otherSchool = School::create([
            'health_center_id' => $otherCenter->id,
            'npsn' => '12345678',
            'name' => 'Sekolah B',
            'level' => 'sd',
        ]);
        $operator = User::factory()->create();
        $operator->roles()->attach(Role::where('name', 'puskesmas_operator')->firstOrFail()->id, [
            'scope_type' => 'puskesmas',
            'scope_id' => $center->id,
        ]);

        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/schools')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $assignedSchool->id);

        $this->assertNotEquals($assignedSchool->id, $otherSchool->id);
    }

    public function test_student_csv_import_requires_preview_and_applies_valid_rows(): void
    {
        $this->seed();
        [$center, $school] = $this->createSchool('A');
        $year = AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2025-2026',
            'starts_on' => '2025-07-01',
            'ends_on' => '2026-06-30',
            'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'school_id' => $school->id,
            'name' => '1A',
            'grade' => 1,
            'academic_level' => 'sd',
        ]);
        $operator = User::factory()->create();
        $operator->roles()->attach(Role::where('name', 'school_operator')->firstOrFail()->id, [
            'scope_type' => 'school',
            'scope_id' => $school->id,
        ]);
        $file = UploadedFile::fake()->createWithContent(
            'students.csv',
            "nisn,name,birth_date,sex,class_name\n1234567890,Siswa Contoh,2018-05-01,,{$class->name}\n",
        );

        $preview = $this->actingAs($operator, 'sanctum')->post('/api/v1/student-imports/preview', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'csv' => $file,
        ])->assertOk()->assertJsonPath('summary.valid_rows', 1);
        $token = $preview->json('preview_token');

        $this->post('/api/v1/student-imports/commit', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'preview_token' => $token,
            'csv' => $file,
        ])->assertCreated()->assertJsonPath('imported_count', 1);

        $this->assertDatabaseHas('students', ['nisn' => '1234567890', 'name' => 'Siswa Contoh']);
        $this->assertDatabaseHas('student_enrollments', ['school_id' => $school->id, 'academic_year_id' => $year->id]);
    }

    public function test_enrollment_transfer_closes_the_old_record_and_creates_the_new_one_atomically(): void
    {
        $this->seed();
        [, $school] = $this->createSchool('A');
        $year = AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2025-2026',
            'starts_on' => '2025-07-01',
            'ends_on' => '2026-06-30',
            'is_active' => true,
        ]);
        $oldClass = SchoolClass::create([
            'school_id' => $school->id,
            'name' => '1A',
            'grade' => 1,
            'academic_level' => 'sd',
        ]);
        $newClass = SchoolClass::create([
            'school_id' => $school->id,
            'name' => '2A',
            'grade' => 2,
            'academic_level' => 'sd',
        ]);
        $student = Student::create(['nisn' => '9876543210', 'name' => 'Siswa Pindah']);
        $enrollment = StudentEnrollment::create([
            'student_id' => $student->id,
            'school_id' => $school->id,
            'school_class_id' => $oldClass->id,
            'academic_year_id' => $year->id,
            'starts_on' => '2025-07-01',
        ]);
        $operator = User::factory()->create();
        $operator->roles()->attach(Role::where('name', 'school_operator')->firstOrFail()->id, [
            'scope_type' => 'school',
            'scope_id' => $school->id,
        ]);

        $this->actingAs($operator, 'sanctum')
            ->postJson("/api/v1/enrollments/{$enrollment->id}/transfer", [
                'school_id' => $school->id,
                'school_class_id' => $newClass->id,
                'academic_year_id' => $year->id,
                'starts_on' => '2025-07-14',
            ])
            ->assertCreated();

        $this->assertSame('2025-07-13', StudentEnrollment::findOrFail($enrollment->id)->ends_on->toDateString());
        $newEnrollment = $student->enrollments()->whereNull('ends_on')->firstOrFail();
        $this->assertSame($newClass->id, $newEnrollment->school_class_id);
        $this->assertSame('2025-07-14', $newEnrollment->starts_on->toDateString());
        $this->assertSame(1, $student->enrollments()->whereNull('ends_on')->count());
    }

    private function createSchool(string $suffix): array
    {
        $center = HealthCenter::create(['code' => 'PKM-'.$suffix, 'name' => 'Puskesmas '.$suffix]);
        $school = School::create([
            'health_center_id' => $center->id,
            'npsn' => $suffix === 'A' ? '10000001' : '10000002',
            'name' => 'Sekolah '.$suffix,
            'level' => 'sd',
        ]);

        return [$center, $school];
    }
}
