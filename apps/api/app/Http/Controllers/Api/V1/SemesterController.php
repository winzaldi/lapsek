<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Services\AuditLogger;
use App\Services\ScopeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    public function index(Request $request, ScopeAccess $scope): JsonResponse
    {
        $query = Semester::whereHas('academicYear', fn ($builder) => $builder
            ->whereIn('school_id', $scope->schoolsFor($request->user())->select('id')));
        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->integer('academic_year_id'));
        }

        return response()->json(['data' => $query->orderBy('academic_year_id')->orderBy('number')->paginate(50)]);
    }

    public function store(Request $request, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'number' => ['required', 'integer', 'between:1,2'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $year = AcademicYear::findOrFail($data['academic_year_id']);
        $scope->assertSchool($request->user(), $year->school);
        abort_unless($request->user()->hasRole('system_admin'), 403, 'Hanya Admin Sistem yang dapat membuat semester.');
        abort_if(
            $data['starts_on'] < $year->starts_on->toDateString() || $data['ends_on'] > $year->ends_on->toDateString(),
            422,
            'Tanggal semester harus berada di dalam tahun ajaran.',
        );
        abort_if(
            Semester::where('academic_year_id', $year->id)
                ->whereDate('starts_on', '<=', $data['ends_on'])
                ->whereDate('ends_on', '>=', $data['starts_on'])
                ->exists(),
            422,
            'Rentang tanggal semester bertumpang tindih.',
        );
        $semester = Semester::create($data);
        $audit->record($request, 'semester.created', $semester);

        return response()->json(['data' => $semester], 201);
    }
}
