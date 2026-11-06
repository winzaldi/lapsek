<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\AuditLogger;
use App\Services\ScopeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolClassController extends Controller
{
    public function index(Request $request, ScopeAccess $scope): JsonResponse
    {
        $schoolId = $request->integer('school_id');
        $query = SchoolClass::query()->whereIn('school_id', $scope->schoolsFor($request->user())->select('id'));
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        return response()->json(['data' => $query->orderBy('school_id')->orderBy('grade')->paginate(50)]);
    }

    public function store(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:64'],
            'grade' => ['required', 'integer', 'between:1,12'],
            'academic_level' => ['required', Rule::in(['sd', 'smp', 'sma', 'slb'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $school = School::findOrFail($data['school_id']);
        $scope->assertSchool($request->user(), $school);
        abort_unless($school->level === $data['academic_level'], 422, 'Jenjang kelas harus sama dengan jenjang sekolah.');
        $class = SchoolClass::create($data);
        $audit->record($request, 'school_class.created', $class);

        return response()->json(['data' => $class], 201);
    }

    public function update(Request $request, SchoolClass $class, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $school = School::findOrFail($class->school_id);
        $scope->assertSchool($request->user(), $school);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('school_classes', 'name')->where('school_id', $school->id)->ignore($class->id)],
            'grade' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'academic_level' => ['sometimes', 'required', Rule::in(['sd', 'smp', 'sma', 'slb'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        abort_unless(($data['academic_level'] ?? $class->academic_level) === $school->level, 422, 'Jenjang kelas harus sama dengan jenjang sekolah.');
        $class->update($data);
        $audit->record($request, 'school_class.updated', $class, metadata: ['fields' => array_keys($data)]);

        return response()->json(['data' => $class->refresh()]);
    }
}
