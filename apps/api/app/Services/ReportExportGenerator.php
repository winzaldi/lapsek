<?php

namespace App\Services;

use App\Models\Examination;
use App\Models\ReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ReportExportGenerator
{
    public const MAX_ROWS = 5000;

    public function generate(ReportExport $export, Collection $examinations, ExaminationIndicatorSchema $schema): string
    {
        if ($examinations->count() > self::MAX_ROWS) {
            throw new RuntimeException('Jumlah baris ekspor melebihi batas.');
        }

        return match ($export->format) {
            'csv' => $this->csv($examinations, $schema),
            'xlsx' => $this->xlsx($examinations, $schema),
            'pdf' => $this->pdf($export, $examinations, $schema),
            default => throw new RuntimeException('Format ekspor tidak didukung.'),
        };
    }

    private function csv(Collection $examinations, ExaminationIndicatorSchema $schema): string
    {
        $stream = fopen('php://temp/maxmemory:5242880', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Tidak dapat menyiapkan file ekspor.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $this->headers($schema), ',', '"', '');
        foreach ($examinations as $examination) {
            fputcsv($stream, $this->row($examination, $schema), ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        if ($contents === false) {
            throw new RuntimeException('Tidak dapat membaca file ekspor.');
        }

        return $contents;
    }

    private function xlsx(Collection $examinations, ExaminationIndicatorSchema $schema): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pemeriksaan');
        foreach ($this->headers($schema) as $column => $header) {
            $sheet->setCellValueExplicit([$column + 1, 1], $header, DataType::TYPE_STRING);
        }
        foreach ($examinations as $rowIndex => $examination) {
            foreach ($this->row($examination, $schema) as $column => $value) {
                $sheet->setCellValueExplicit(
                    [$column + 1, $rowIndex + 2],
                    (string) $value,
                    DataType::TYPE_STRING,
                );
            }
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $temporaryPath = tempnam(sys_get_temp_dir(), 'laos-xlsx-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Tidak dapat menyiapkan file XLSX.');
        }

        try {
            (new Xlsx($spreadsheet))->save($temporaryPath);
            $contents = file_get_contents($temporaryPath);
            if ($contents === false) {
                throw new RuntimeException('Tidak dapat membaca file XLSX.');
            }

            return $contents;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unlink($temporaryPath);
        }
    }

    private function pdf(ReportExport $export, Collection $examinations, ExaminationIndicatorSchema $schema): string
    {
        if ($examinations->count() > 100) {
            throw new RuntimeException('PDF dibatasi maksimal 100 pemeriksaan.');
        }

        return Pdf::loadView('reports.export-pdf', [
            'export' => $export,
            'examinations' => $examinations,
            'indicatorKeys' => $schema->keys(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->setOption('isRemoteEnabled', false)->output();
    }

    private function headers(ExaminationIndicatorSchema $schema): array
    {
        return [
            'Nama siswa', 'NISN', 'Jenis kelamin', 'Sekolah', 'Kelas', 'Tahun ajaran', 'Semester',
            'Tanggal pemeriksaan', 'Pemeriksa', 'Status', 'Versi', 'IMT',
            ...$schema->keys(),
        ];
    }

    private function row(Examination $examination, ExaminationIndicatorSchema $schema): array
    {
        $observations = $examination->observations->keyBy('indicator_key');
        $row = [
            $examination->student->name,
            $examination->student->nisn ?? '',
            $examination->student->sex ?? '',
            $examination->school_name_snapshot,
            $examination->class_name_snapshot ?? $examination->schoolClass?->name ?? '',
            $examination->semester->academicYear->name,
            'Semester '.$examination->semester->number,
            $examination->examined_at->toDateString(),
            $examination->examiner_name,
            $examination->status,
            $examination->version,
            $examination->bmi ?? '',
        ];
        foreach ($schema->keys() as $key) {
            $observation = $observations->get($key);
            $value = ! $observation || $observation->status === 'not_examined'
                ? 'Belum diperiksa'
                : (is_scalar($observation->value) ? (string) $observation->value : json_encode($observation->value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $row[] = $this->spreadsheetSafe($value);
        }

        return array_map($this->spreadsheetSafe(...), $row);
    }

    private function spreadsheetSafe(mixed $value): string
    {
        if (! is_string($value)) {
            $value = (string) ($value ?? '');
        }
        $value = str_replace(["\0", "\r"], ['', ' '], $value);

        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
