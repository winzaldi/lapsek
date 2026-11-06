<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\ReportExport;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportExportAccess
{
    public function targetSchoolIds(User $user, array $filters, ScopeAccess $scope): array
    {
        $schools = $scope->schoolsFor($user);
        if (isset($filters['school_id'])) {
            abort_unless((clone $schools)->whereKey($filters['school_id'])->exists(), 403, 'Akses sekolah tidak diizinkan.');
            $schools->whereKey($filters['school_id']);
        }

        if (isset($filters['level'])) {
            $schools->where('level', $filters['level']);
        }

        $yearSchools = AcademicYear::query()
            ->where('name', $filters['academic_year'])
            ->whereIn('school_id', $schools->select('id'))
            ->select('school_id')
            ->distinct()
            ->pluck('school_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        if (isset($filters['school_class_id'])) {
            $class = SchoolClass::findOrFail($filters['school_class_id']);
            abort_unless((clone $scope->schoolsFor($user))->whereKey($class->school_id)->exists(), 403, 'Akses kelas tidak diizinkan.');
            abort_if(
                isset($filters['school_id']) && (int) $class->school_id !== (int) $filters['school_id'],
                422,
                'Kelas bukan milik sekolah yang dipilih.',
            );
            abort_if(! in_array((int) $class->school_id, $yearSchools, true), 422, 'Kelas tidak memiliki tahun ajaran yang dipilih.');
        }

        return $yearSchools;
    }

    public function assertCanCreate(User $user, array $filters): string
    {
        $canViewFinalized = $user->hasRole('system_admin') || $user->hasRole('puskesmas_operator');
        abort_if(
            ! $canViewFinalized && ($filters['status'] ?? 'published') !== 'published',
            403,
            'Sekolah hanya dapat mengekspor hasil yang telah dipublikasikan.',
        );

        return ($filters['status'] ?? null) === 'finalized'
            || (! isset($filters['status']) && $canViewFinalized)
            ? 'puskesmas'
            : 'school';
    }

    public function assertCanAccess(User $user, ReportExport $export, ScopeAccess $scope): void
    {
        abort_unless($user->is_active, 403, 'Akun tidak aktif.');
        if ($export->required_role === 'puskesmas') {
            abort_unless($user->hasRole('system_admin') || $user->hasRole('puskesmas_operator'), 403, 'Akses ekspor tidak diizinkan.');
        }
        if ($user->hasRole('system_admin')) {
            return;
        }

        $allowedIds = $scope->schoolsFor($user)->pluck('id')->map(static fn ($id): int => (int) $id);
        $targetIds = collect($export->school_ids ?? [])->map(static fn ($id): int => (int) $id);
        abort_unless($targetIds->isNotEmpty() && $targetIds->diff($allowedIds)->isEmpty(), 403, 'Akses ekspor tidak diizinkan.');
    }

    public function historyFor(User $user, ScopeAccess $scope): Builder
    {
        $query = ReportExport::query();
        if ($user->hasRole('system_admin')) {
            return $query;
        }

        if (! $user->hasRole('puskesmas_operator')) {
            $query->where('required_role', 'school');
        }

        $allowedIds = $scope->schoolsFor($user)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        abort_if($allowedIds === [], 403, 'Tidak ada sekolah dalam cakupan akun.');

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $query->whereRaw('JSON_CONTAINS(?, school_ids)', [json_encode($allowedIds, JSON_THROW_ON_ERROR)]);
        } elseif ($driver === 'sqlite') {
            $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
            $query->whereRaw(
                "NOT EXISTS (SELECT 1 FROM json_each(report_exports.school_ids) AS target_school WHERE CAST(target_school.value AS INTEGER) NOT IN ({$placeholders}))",
                $allowedIds,
            );
        } else {
            $query->whereIn('requested_by_user_id', [$user->id]);
        }

        return $query;
    }

    public function query(ReportExport $export, User $user, ScopeAccess $scope): Builder
    {
        $this->assertCanAccess($user, $export, $scope);
        $filters = $export->filters;
        $statuses = isset($filters['status'])
            ? [$filters['status']]
            : ($export->required_role === 'puskesmas' ? ['finalized', 'published'] : ['published']);

        return Examination::query()
            ->with([
                'student:id,nisn,name,birth_date,sex',
                'schoolClass:id,name',
                'semester:id,academic_year_id,number',
                'semester.academicYear:id,name',
                'observations',
            ])
            ->whereIn('school_id', $export->school_ids)
            ->whereIn('status', $statuses)
            ->whereHas('semester.academicYear', fn (Builder $year) => $year->where('name', $filters['academic_year']))
            ->when(isset($filters['school_class_id']), fn (Builder $query) => $query->where('school_class_id', $filters['school_class_id']))
            ->when(isset($filters['semester']), fn (Builder $query) => $query->whereHas(
                'semester',
                fn (Builder $semester) => $semester->where('number', $filters['semester']),
            ))
            ->orderBy('school_id')
            ->orderBy('student_id')
            ->orderBy('semester_id');
    }
}
