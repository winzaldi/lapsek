<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAccountLink;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ScopeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentAccountLinkController extends Controller
{
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id', 'unique:student_account_links,student_id'],
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:student_account_links,user_id'],
        ]);
        $student = Student::findOrFail($data['student_id']);
        $user = User::findOrFail($data['user_id']);
        abort_unless($user->is_active, 422, 'Akun siswa harus aktif.');
        abort_unless($user->hasRole('student') && $user->roles()->count() === 1, 422, 'Akun harus memiliki peran Siswa saja.');
        abort_if($user->linkedStudent()->exists(), 422, 'Akun sudah tertaut ke siswa lain.');
        $link = DB::transaction(function () use ($student, $user): StudentAccountLink {
            return StudentAccountLink::create([
                'student_id' => $student->id,
                'user_id' => $user->id,
                'status' => 'pending',
            ]);
        });
        $audit->record($request, 'student_account_link.created', $link, metadata: ['student_id' => $student->id]);

        return response()->json(['data' => $link], 201);
    }

    public function verify(Request $request, StudentAccountLink $studentAccountLink, ScopeAccess $scope, AuditLogger $audit): JsonResponse
    {
        abort_unless($studentAccountLink->status === 'pending', 422, 'Tautan siswa bukan dalam status menunggu verifikasi.');
        abort_unless($studentAccountLink->student->enrollments()
            ->whereNull('ends_on')
            ->get()
            ->contains(fn ($enrollment) => $scope->canAccessSchool($request->user(), $enrollment->school)), 403);

        $studentAccountLink->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);
        $audit->record($request, 'student_account_link.verified', $studentAccountLink);

        return response()->json(['data' => $studentAccountLink->refresh()]);
    }
}
