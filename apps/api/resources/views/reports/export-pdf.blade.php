<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { color: #233329; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { font-size: 16px; margin: 0 0 5px; }
        .meta { color: #526459; margin-bottom: 15px; }
        .examination { border: 1px solid #cbd5ce; margin-bottom: 12px; page-break-inside: avoid; }
        .heading { background: #edf4ef; font-size: 10px; font-weight: bold; padding: 7px; }
        .details { border-collapse: collapse; width: 100%; }
        .details td { border-top: 1px solid #e1e7e2; padding: 4px 6px; vertical-align: top; width: 25%; }
        .details strong { color: #526459; }
        .footer { color: #65756a; margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Laporan Pemeriksaan Kesehatan UKS</h1>
    <div class="meta">
        Tahun ajaran {{ $export->filters['academic_year'] }}
        @if (isset($export->filters['semester']))
            · Semester {{ $export->filters['semester'] }}
        @else
            · Rekap tahunan
        @endif
        · Dibuat {{ $generatedAt->format('d-m-Y H:i') }}
        · Snapshot {{ ($export->snapshot_at ?? $generatedAt)->format('d-m-Y H:i') }}
    </div>
    @forelse ($examinations as $examination)
        <section class="examination">
            <div class="heading">{{ $examination->student->name }} · {{ $examination->school_name_snapshot }} · {{ $examination->class_name_snapshot ?? 'Kelas tidak tersedia' }}</div>
            <table class="details">
                <tr>
                    <td><strong>NISN</strong><br>{{ $examination->student->nisn ?? '—' }}</td>
                    <td><strong>Tanggal / Semester</strong><br>{{ $examination->examined_at->format('d-m-Y') }} · Semester {{ $examination->semester->number }}</td>
                    <td><strong>Pemeriksa / Status</strong><br>{{ $examination->examiner_name }} · {{ $examination->status }}</td>
                    <td><strong>IMT</strong><br>{{ $examination->bmi ?? 'Belum dihitung' }}</td>
                </tr>
                @php($observations = $examination->observations->keyBy('indicator_key'))
                @foreach (array_chunk($indicatorKeys, 4) as $indicatorGroup)
                    <tr>
                        @foreach ($indicatorGroup as $key)
                            @php($observation = $observations->get($key))
                            <td><strong>{{ $key }}</strong><br>
                                @if (!$observation || $observation->status === 'not_examined')
                                    Belum diperiksa
                                @elseif (is_scalar($observation->value))
                                    {{ (string) $observation->value }}
                                @else
                                    {{ json_encode($observation->value, JSON_UNESCAPED_UNICODE) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </section>
    @empty
        <p>Tidak ada data pemeriksaan pada filter yang dipilih.</p>
    @endforelse
    <p class="footer">Dokumen rahasia berisi data kesehatan siswa. Simpan dan distribusikan sesuai kewenangan.</p>
</body>
</html>
