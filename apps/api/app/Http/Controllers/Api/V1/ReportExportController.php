<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Services\AuditLogger;
use App\Services\ReportExportAccess;
use App\Services\ReportExportGenerator;
use App\Services\ScopeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportExportController extends Controller
{
    public function index(Request $request, ReportExportAccess $access, ScopeAccess $scope): JsonResponse
    {
        $exports = $access->historyFor($request->user(), $scope)
            ->latest()
            ->paginate(20)
            ->through(fn (ReportExport $export): array => $this->resource($export));

        return response()->json(['data' => $exports]);
    }

    public function store(
        Request $request,
        ReportExportAccess $access,
        ReportExportGenerator $generator,
        ScopeAccess $scope,
        AuditLogger $audit,
    ): JsonResponse {
        $filters = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['sometimes', 'integer', 'between:1,2'],
            'school_id' => ['sometimes', 'integer', 'exists:schools,id'],
            'school_class_id' => ['sometimes', 'integer', 'exists:school_classes,id'],
            'level' => ['sometimes', 'in:sd,smp,sma,slb'],
            'status' => ['sometimes', 'in:finalized,published'],
            'format' => ['required', 'in:csv,xlsx,pdf'],
        ]);
        $user = $request->user();
        $requiredRole = $access->assertCanCreate($user, $filters);
        $schoolIds = $access->targetSchoolIds($user, $filters, $scope);
        abort_if($schoolIds === [], 422, 'Tidak ada sekolah dengan tahun ajaran tersebut dalam cakupan Anda.');

        $export = new ReportExport([
            'requested_by_user_id' => $user->id,
            'filters' => $filters,
            'school_ids' => $schoolIds,
            'format' => $filters['format'],
            'required_role' => $requiredRole,
        ]);
        $count = $access->query($export, $user, $scope)->count();
        $maximum = $filters['format'] === 'pdf' ? 100 : ReportExportGenerator::MAX_ROWS;
        abort_if($count > $maximum, 422, "Ekspor {$filters['format']} dibatasi maksimal {$maximum} pemeriksaan. Persempit filter laporan.");

        $export->forceFill([
            'status' => 'queued',
            'disk' => 'local',
            'expires_at' => now()->addDays(config('report-exports.retention_days')),
        ])->save();
        $audit->record($request, 'report.export_requested', ReportExport::class, metadata: [
            'report_export_id' => $export->id,
            'format' => $export->format,
            'filters' => $filters,
            'target_school_count' => count($schoolIds),
            'expires_at' => $export->expires_at->toIso8601String(),
        ]);
        GenerateReportExport::dispatch($export->id)->onQueue('report-exports')->afterCommit();

        return response()->json(['data' => $this->resource($export->refresh())], 202);
    }

    public function show(Request $request, ReportExport $reportExport, ReportExportAccess $access, ScopeAccess $scope): JsonResponse
    {
        $access->assertCanAccess($request->user(), $reportExport, $scope);

        return response()->json(['data' => $this->resource($reportExport)]);
    }

    public function download(
        Request $request,
        ReportExport $reportExport,
        ReportExportAccess $access,
        ScopeAccess $scope,
        AuditLogger $audit,
    ) {
        $access->assertCanAccess($request->user(), $reportExport, $scope);
        abort_if($reportExport->expires_at->isPast() || $reportExport->status === 'expired', 410, 'File ekspor telah kedaluwarsa.');
        abort_unless($reportExport->status === 'completed' && $reportExport->path, 409, 'File ekspor belum siap diunduh.');
        abort_unless(Storage::disk($reportExport->disk)->exists($reportExport->path), 410, 'File ekspor tidak tersedia.');

        $audit->record($request, 'report.export_downloaded', ReportExport::class, metadata: [
            'report_export_id' => $reportExport->id,
            'format' => $reportExport->format,
        ]);

        $contentTypes = [
            'csv' => 'text/csv; charset=UTF-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
        ];
        $filename = 'laos-pemeriksaan-'.$reportExport->filters['academic_year']
            .(isset($reportExport->filters['semester']) ? '-semester-'.$reportExport->filters['semester'] : '-tahunan')
            .'.'.$reportExport->format;

        return Storage::disk($reportExport->disk)->download($reportExport->path, $filename, [
            'Content-Type' => $contentTypes[$reportExport->format],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resource(ReportExport $export): array
    {
        return [
            'id' => $export->id,
            'format' => $export->format,
            'status' => $export->status,
            'filters' => $export->filters,
            'row_count' => $export->row_count,
            'failure_message' => $export->failure_message,
            'snapshot_at' => $export->snapshot_at?->toIso8601String(),
            'created_at' => $export->created_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            'can_download' => $export->status === 'completed' && $export->expires_at?->isFuture(),
        ];
    }
}
