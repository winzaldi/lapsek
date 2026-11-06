<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ScopeAccess
{
    public function schoolsFor(User $user): Builder
    {
        $query = School::query();
        if ($user->hasRole('system_admin')) {
            return $query;
        }

        $schoolIds = $user->roles()
            ->wherePivot('scope_type', 'school')
            ->wherePivotNotNull('scope_id')
            ->whereIn('name', ['school_operator'])
            ->get()->map(fn ($role) => $role->pivot->scope_id)->unique();

        $healthCenterIds = $user->roles()
            ->wherePivot('scope_type', 'puskesmas')
            ->wherePivotNotNull('scope_id')
            ->whereIn('name', ['puskesmas_operator'])
            ->get()->map(fn ($role) => $role->pivot->scope_id)->unique();

        return $query->where(function (Builder $builder) use ($schoolIds, $healthCenterIds): void {
            if ($schoolIds->isNotEmpty()) {
                $builder->whereIn('id', $schoolIds);
            }
            if ($healthCenterIds->isNotEmpty()) {
                $method = $schoolIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $builder->{$method}('health_center_id', $healthCenterIds);
            }
            if ($schoolIds->isEmpty() && $healthCenterIds->isEmpty()) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    public function canAccessSchool(User $user, School $school): bool
    {
        return $user->hasRole('system_admin')
            || $this->schoolsFor($user)->whereKey($school->getKey())->exists();
    }

    public function assertSchool(User $user, School $school): void
    {
        abort_unless($this->canAccessSchool($user, $school), 403, 'Akses sekolah tidak diizinkan.');
    }
}
