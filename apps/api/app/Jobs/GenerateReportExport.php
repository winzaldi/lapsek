<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Models\User;
use App\Services\ExaminationIndicatorSchema;
use App\Services\ReportExportAccess;
use App\Services\ReportExportGenerator;
use App\Services\ScopeAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public string $exportId) {}

    public function handle(
        ReportExportAccess $access,
        ReportExportGenerator $generator,
        ScopeAccess $scope,
        ExaminationIndicatorSchema $schema,
    ): void {
        $export = ReportExport::find($this->exportId);
        if (! $export || $export->status === 'expired') {
            return;
        }
        if ($export->expires_at->isPast()) {
            $export->forceFill(['status' => 'expired', 'path' => null])->save();

            return;
        }
        $export->forceFill(['status' => 'processing', 'started_at' => now(), 'failure_message' => null])->save();
        $requester = User::find($export->requested_by_user_id);
        if (! $requester || ! $requester->is_active) {
            throw new RuntimeException('Peminta ekspor tidak aktif.');
        }

        $examinations = $access->query($export, $requester, $scope)
            ->limit(ReportExportGenerator::MAX_ROWS + 1)
            ->get();
        if ($export->format === 'pdf' && $examinations->count() > 100) {
            throw new RuntimeException('PDF melebihi batas ukuran yang diizinkan.');
        }
        if ($examinations->count() > ReportExportGenerator::MAX_ROWS) {
            throw new RuntimeException('Ekspor melebihi batas ukuran yang diizinkan.');
        }

        $export->snapshot_at = now();
        $contents = $generator->generate($export, $examinations, $schema);
        $path = 'report-exports/'.$export->id.'.'.$export->format;
        if (! Storage::disk($export->disk)->put($path, $contents, ['visibility' => 'private'])) {
            throw new RuntimeException('Penyimpanan file ekspor gagal.');
        }

        $export->forceFill([
            'path' => $path,
            'row_count' => $examinations->count(),
            'snapshot_at' => $export->snapshot_at,
            'status' => 'completed',
            'completed_at' => now(),
        ])->save();
        DB::table('audit_logs')->insert([
            'actor_id' => $requester->id,
            'action' => 'report.export_completed',
            'resource_type' => ReportExport::class,
            'metadata' => json_encode([
                'report_export_id' => $export->id,
                'format' => $export->format,
                'row_count' => $examinations->count(),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $export = ReportExport::find($this->exportId);
        if ($export) {
            if ($export->path) {
                if (! Storage::disk($export->disk)->delete($export->path)) {
                    Log::warning('Failed report export file cleanup.', ['report_export_id' => $this->exportId]);
                }
            }
            $export->forceFill([
                'status' => 'failed',
                'path' => null,
                'failure_message' => 'Ekspor gagal diproses. Silakan buat ekspor baru.',
                'completed_at' => now(),
            ])->save();
        }
        Log::error('Report export job failed.', [
            'report_export_id' => $this->exportId,
            'exception' => $exception::class,
        ]);
    }
}
