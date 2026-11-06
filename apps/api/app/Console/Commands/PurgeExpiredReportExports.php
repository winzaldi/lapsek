<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredReportExports extends Command
{
    protected $signature = 'reports:purge-expired-exports';

    protected $description = 'Delete expired private report files and mark their records as expired';

    public function handle(): int
    {
        ReportExport::query()
            ->where('expires_at', '<=', now())
            ->whereIn('status', ['queued', 'processing', 'completed'])
            ->orderBy('created_at')
            ->chunkById(100, function ($exports): void {
                foreach ($exports as $export) {
                    if ($export->path && ! Storage::disk($export->disk)->delete($export->path)) {
                        $this->error('Gagal menghapus file ekspor '.$export->id.'.');

                        continue;
                    }
                    $export->forceFill([
                        'status' => 'expired',
                        'path' => null,
                    ])->save();
                }
            });

        return self::SUCCESS;
    }
}
