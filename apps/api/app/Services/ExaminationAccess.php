<?php

namespace App\Services;

use App\Models\Examination;
use App\Models\School;
use App\Models\Student;
use App\Models\User;

class ExaminationAccess
{
    public function __construct(private readonly ScopeAccess $scopeAccess) {}

    public function assertCanManage(User $user, School $school, string $permission = 'examinations.manage'): void
    {
        abort_unless($user->hasRole('puskesmas_operator'), 403, 'Hanya Operator Puskesmas yang dapat mengelola pemeriksaan.');
        abort_unless($user->hasPermission($permission), 403, 'Akses tidak diizinkan.');
        $this->scopeAccess->assertSchool($user, $school);
    }

    public function assertCanView(User $user, Examination $examination): void
    {
        abort_unless($user->hasPermission('examinations.view'), 403, 'Akses pemeriksaan tidak diizinkan.');
        $this->scopeAccess->assertSchool($user, $examination->school);
        if (! $user->hasRole('puskesmas_operator')) {
            abort_unless($examination->status === 'published', 404, 'Pemeriksaan tidak ditemukan.');
        }
    }

    public function assertCanViewStudentHistory(User $user, Student $student): void
    {
        if ($user->hasRole('student')) {
            $link = $user->linkedStudent()->first();
            abort_unless(
                $link && $link->status === 'verified' && $link->student_id === $student->id,
                404,
                'Riwayat pemeriksaan tidak ditemukan.',
            );

            return;
        }

        abort_unless($user->hasPermission('examinations.view'), 403, 'Akses riwayat pemeriksaan tidak diizinkan.');
        $hasAccessibleEnrollment = $student->enrollments()
            ->with('school')
            ->get()
            ->contains(fn ($enrollment) => $this->scopeAccess->canAccessSchool($user, $enrollment->school));
        abort_unless($hasAccessibleEnrollment, 404, 'Riwayat pemeriksaan tidak ditemukan.');
    }
}
