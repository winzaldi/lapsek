<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\School;
use App\Services\AuditLogger;
use App\Services\ScopeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicYearController extends Controller
{
    public function index(Request $request, ScopeAccess $scope): JsonResponse
    {
        $query = AcademicYear::whereIn('school_id', $scope->schoolsFor($request->user())->select('id'));
        if ($request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }

        return response()->json(['data' => $query->orderByDesc('starts_on')->paginate(50)]);
    }

    public function store(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => [
                'required',
                'regex:/^\d{4}-\d{4}$/',
                'max:16',
                Rule::unique('academic_years')->where(fn ($query) => $query->where('school_id', $request->input('school_id'))),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $school = School::findOrFail($data['school_id']);
        $scope->assertSchool($request->user(), $school);
        abort_unless($request->user()->hasRole('system_admin'), 403, 'Hanya Admin Sistem yang dapat membuat tahun ajaran.');
        abort_if(
            AcademicYear::where('school_id', $school->id)
                ->whereDate('starts_on', '<=', $data['ends_on'])
                ->whereDate('ends_on', '>=', $data['starts_on'])
                ->exists(),
            422,
            'Rentang tanggal bertumpang tindih dengan tahun ajaran sekolah yang sudah ada.',
        );

        $year = DB::transaction(function () use ($data): AcademicYear {
            if ($data['is_active'] ?? false) {
                AcademicYear::where('school_id', $data['school_id'])->update(['is_active' => false]);
            }

            return AcademicYear::create($data);
        });
        $audit->record($request, 'academic_year.created', $year);

        return response()->json(['data' => $year], 201);
    }
}
