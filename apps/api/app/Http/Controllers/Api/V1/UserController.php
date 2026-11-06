<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HealthCenter;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::with('roles:id,name,label')->orderBy('name')->paginate(50),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'role' => ['required', Rule::in(['system_admin', 'puskesmas_operator', 'school_operator', 'student'])],
            'scope_id' => ['required_if:role,puskesmas_operator,school_operator', 'nullable', 'integer'],
        ]);
        $scope = $this->validatedScope($data['role'], $data['scope_id'] ?? null);

        $user = DB::transaction(function () use ($data, $scope, $request): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $this->assignRole($user, $data['role'], $scope, $request->user()->id);

            return $user;
        });
        $audit->record($request, 'user.created', $user, metadata: ['role' => $data['role']]);

        return response()->json(['data' => $user->load('roles:id,name,label')], 201);
    }

    public function updateRole(Request $request, User $user, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['system_admin', 'puskesmas_operator', 'school_operator', 'student'])],
            'scope_id' => ['required_if:role,puskesmas_operator,school_operator', 'nullable', 'integer'],
        ]);
        abort_if($user->linkedStudent()->exists() && $data['role'] !== 'student', 422, 'Peran akun siswa tertaut tidak dapat diubah.');
        if ($user->hasRole('system_admin') && $data['role'] !== 'system_admin') {
            abort_if(
                User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('name', 'system_admin'))->count() <= 1,
                422,
                'Admin Sistem aktif terakhir tidak dapat kehilangan peran Admin Sistem.',
            );
        }
        $scope = $this->validatedScope($data['role'], $data['scope_id'] ?? null);

        DB::transaction(function () use ($request, $user, $data, $scope): void {
            $user->roles()->detach();
            $this->assignRole($user, $data['role'], $scope, $request->user()->id);
        });
        $audit->record($request, 'user.role_changed', $user, metadata: ['role' => $data['role']]);

        return response()->json(['data' => $user->load('roles:id,name,label')]);
    }

    public function setStatus(Request $request, User $user, AuditLogger $audit): JsonResponse
    {
        abort_if($request->user()->is($user) && ! $request->boolean('is_active'), 422, 'Admin tidak dapat menonaktifkan akunnya sendiri.');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        if (! $data['is_active'] && $user->hasRole('system_admin')) {
            abort_if(
                User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('name', 'system_admin'))->count() <= 1,
                422,
                'Admin Sistem aktif terakhir tidak dapat dinonaktifkan.',
            );
        }
        $user->update($data);
        if (! $data['is_active']) {
            $user->tokens()->delete();
        }
        $audit->record($request, 'user.status_changed', $user, metadata: ['is_active' => $data['is_active']]);

        return response()->json(['data' => $user->refresh()]);
    }

    private function validatedScope(string $roleName, ?int $scopeId): array
    {
        if ($roleName === 'system_admin') {
            abort_if($scopeId !== null, 422, 'Admin Sistem tidak menggunakan scope.');

            return [null, null];
        }
        if ($roleName === 'puskesmas_operator') {
            abort_unless($scopeId && HealthCenter::whereKey($scopeId)->exists(), 422, 'Puskesmas tidak ditemukan.');

            return ['puskesmas', $scopeId];
        }
        if ($roleName === 'school_operator') {
            abort_unless($scopeId && School::whereKey($scopeId)->exists(), 422, 'Sekolah tidak ditemukan.');

            return ['school', $scopeId];
        }
        if ($roleName === 'student') {
            abort_if($scopeId !== null, 422, 'Siswa hanya dapat diakses melalui profil tertaut.');

            return [null, null];
        }

        return [null, null];
    }

    private function assignRole(User $user, string $roleName, array $scope, int $assignedBy): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id, [
            'scope_type' => $scope[0],
            'scope_id' => $scope[1],
            'assigned_by' => $assignedBy,
        ]);
    }
}
