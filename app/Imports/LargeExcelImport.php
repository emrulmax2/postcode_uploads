<?php

namespace App\Imports;

use App\Models\BigData;
use App\Models\Import;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ShouldQueueWithoutChain;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;

class LargeExcelImport implements ToCollection, WithHeadingRow, WithChunkReading, WithBatchInserts, ShouldQueueWithoutChain, WithEvents
{
    public int $tries = 3;

    public int $timeout = 1200;

    public array $backoff = [60, 120, 300];

    public function __construct(private readonly int $importId)
    {
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $payload[] = [
                'import_id' => $this->importId,
                'row_data' => json_encode($row->toArray(), JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        BigData::insert($payload);

        Import::whereKey($this->importId)->increment('processed_rows', $rows->count());
    }

    public function chunkSize(): int
    {
        return 1500;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event): void {
                $totals = $event->getReader()->getTotalRows();
                $firstSheetRows = is_array($totals) ? (reset($totals) ?: null) : null;

                Import::whereKey($this->importId)->update([
                    'status' => 'processing',
                    'total_rows' => $firstSheetRows,
                ]);
            },
            AfterImport::class => function (AfterImport $event): void {
                Import::whereKey($this->importId)->update([
                    'status' => 'completed',
                ]);
            },
            ImportFailed::class => function (ImportFailed $event): void {
                Import::whereKey($this->importId)->update([
                    'status' => 'failed',
                    'error' => $event->getException()->getMessage(),
                ]);
            },
        ];
    }
}
