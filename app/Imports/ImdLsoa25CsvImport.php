<?php

namespace App\Imports;

use App\Models\Import;
use App\Models\ImdLsoa25;
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

class ImdLsoa25CsvImport implements ToCollection, WithHeadingRow, WithChunkReading, WithBatchInserts, ShouldQueueWithoutChain, WithEvents
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
            $data = array_change_key_case($row->toArray(), CASE_LOWER);

            $payload[] = [
                'import_id' => $this->importId,
                'lsoa_code_2021' => $this->normalize($data['lsoa_code_2021'] ?? null),
                'lsoa_name_2021' => $this->normalize($data['lsoa_name_2021'] ?? null),
                'local_authority_district_code_2024' => $this->normalize($data['local_authority_district_code_2024'] ?? null),
                'local_authority_district_name_2024' => $this->normalize($data['local_authority_district_name_2024'] ?? null),
                'imd_rank' => $this->normalize($data['index_of_multiple_deprivation_imd_rank_where_1_is_most_deprived'] ?? null),
                'imd_decile' => $this->normalize($data['index_of_multiple_deprivation_imd_decile_where_1_is_most_deprived_10_of_lsoas'] ?? null),
                'imd_quantile_2025' => $this->normalizeInt($data['imd_quantile_2025'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ImdLsoa25::insert($payload);

        Import::whereKey($this->importId)->increment('processed_rows', $rows->count());
    }

    public function chunkSize(): int
    {
        return 1000;
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

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : (int) $string;
    }
}