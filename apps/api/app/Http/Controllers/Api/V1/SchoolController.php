<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HealthCenter;
use App\Models\School;
use App\Services\AuditLogger;
use App\Services\ScopeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolController extends Controller
{
    public function index(Request $request, ScopeAccess $scope): JsonResponse
    {
        $query = $scope->schoolsFor($request->user())->with('healthCenter:id,name')->orderBy('name');

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function store(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'health_center_id' => ['required', 'integer', 'exists:health_centers,id'],
            'npsn' => ['nullable', 'string', 'max:16', 'unique:schools,npsn'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::in(['sd', 'smp', 'sma', 'slb'])],
            'school_type' => ['sometimes', Rule::in(['regular', 'equivalent', 'special_needs'])],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        abort_unless(HealthCenter::whereKey($data['health_center_id'])->exists(), 422);
        $school = School::create($data);
        $audit->record($request, 'school.created', $school);

        return response()->json(['data' => $school], 201);
    }

    public function update(Request $request, School $school, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $scope->assertSchool($request->user(), $school);
        abort_unless($request->user()->hasRole('system_admin'), 403, 'Hanya Admin Sistem yang dapat mengubah master sekolah.');
        $data = $request->validate([
            'health_center_id' => ['sometimes', 'required', 'integer', 'exists:health_centers,id'],
            'npsn' => ['sometimes', 'nullable', 'string', 'max:16', 'unique:schools,npsn,'.$school->id],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'level' => ['sometimes', 'required', Rule::in(['sd', 'smp', 'sma', 'slb'])],
            'school_type' => ['sometimes', Rule::in(['regular', 'equivalent', 'special_needs'])],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['level']) && $data['level'] !== $school->level) {
            abort_if($school->classes()->exists(), 422, 'Jenjang tidak dapat diubah setelah kelas dibuat.');
        }
        $school->update($data);
        $audit->record($request, 'school.updated', $school, metadata: ['fields' => array_keys($data)]);

        return response()->json(['data' => $school->refresh()]);
    }
}
