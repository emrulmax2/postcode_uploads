<?php

namespace App\Imports;

use App\Models\Import;
use App\Models\PostcodeRecord;
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

class PostcodeCsvImport implements ToCollection, WithHeadingRow, WithChunkReading, WithBatchInserts, ShouldQueueWithoutChain, WithEvents
{
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
                'postcode' => $this->normalize($data['postcode'] ?? null),
                'postcode2' => $this->normalize($data['postcode2'] ?? null),
                'polar4_quintile' => $this->normalize($data['polar4_quintile'] ?? null),
                'polar3_quintile' => $this->normalize($data['polar3_quintile'] ?? null),
                'reason_removed_polar' => $this->normalize($data['reason_removed_polar'] ?? null),
                'tundra_msoa_quintile' => $this->normalize($data['tundra_msoa_quintile'] ?? null),
                'reason_removed_tundra_msoa' => $this->normalize($data['reason_removed_tundra_msoa'] ?? null),
                'tundra_lsoa_quintile' => $this->normalize($data['tundra_lsoa_quintile'] ?? null),
                'reason_removed_tundra_lsoa' => $this->normalize($data['reason_removed_tundra_lsoa'] ?? null),
                'adult_he_2011_quintile' => $this->normalize($data['adult_he_2011_quintile'] ?? null),
                'reason_removed_adult_he_2011' => $this->normalize($data['reason_removed_adult_he_2011'] ?? null),
                'gaps_gcse_quintile' => $this->normalize($data['gaps_gcse_quintile'] ?? null),
                'gaps_gcse_ethnicity_quintile' => $this->normalize($data['gaps_gcse_ethnicity_quintile'] ?? null),
                'reason_removed_gaps' => $this->normalize($data['reason_removed_gaps'] ?? null),
                'uni_connect_target_ward' => $this->normalize($data['uni_connect_target_ward'] ?? null),
                'postcode_status' => $this->normalize($data['postcode_status'] ?? null),
                'msoa_current' => $this->normalize($data['msoa_current'] ?? null),
                'msoa_name' => $this->normalize($data['msoa_name'] ?? null),
                'msoa_polar' => $this->normalize($data['msoa_polar'] ?? null),
                'msoa_tundra' => $this->normalize($data['msoa_tundra'] ?? null),
                'msoa_adult_he_2011' => $this->normalize($data['msoa_adult_he_2011'] ?? null),
                'lsoa_current' => $this->normalize($data['lsoa_current'] ?? null),
                'lsoa_name' => $this->normalize($data['lsoa_name'] ?? null),
                'lsoa_tundra' => $this->normalize($data['lsoa_tundra'] ?? null),
                'cas_ward_current' => $this->normalize($data['cas_ward_current'] ?? null),
                'cas_ward_name' => $this->normalize($data['cas_ward_name'] ?? null),
                'cas_ward_measures' => $this->normalize($data['cas_ward_measures'] ?? null),
                'itl2_code' => $this->normalize($data['itl2_code'] ?? null),
                'itl2_name' => $this->normalize($data['itl2_name'] ?? null),
                'country' => $this->normalize($data['country'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        PostcodeRecord::insert($payload);

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
}
