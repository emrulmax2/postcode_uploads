<?php
<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\ImdLsoa25;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImdLsoa25CsvChunk implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 1200;

    public array $backoff = [60, 120, 300];

    /**
     * @param array<int, string> $header
     */
    public function __construct(
        private readonly int $importId,
        private readonly string $csvPath,
        private readonly array $header,
        private readonly int $startLine,
        private readonly int $endLine
    ) {
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $file = new \SplFileObject($this->csvPath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->seek($this->startLine);

        $now = now();
        $payload = [];
        $processed = 0;
        $failed = 0;
        $headerCount = count($this->header);

        for ($line = $this->startLine; $line <= $this->endLine && !$file->eof(); $line++) {
            $row = $file->current();
            $file->next();

            if ($row === false || $row === [null]) {
                continue;
            }

            $row = array_pad($row, $headerCount, null);
            $assoc = array_combine($this->header, array_slice($row, 0, $headerCount));

            if ($assoc === false) {
                $failed++;
                continue;
            }

            $data = array_change_key_case($assoc, CASE_LOWER);

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $payload[] = [
                'import_id' => $this->importId,
                'lsoa_code_2021' => $this->normalize($data['lsoa code (2021)'] ?? null),
                'lsoa_name_2021' => $this->normalize($data['lsoa name (2021)'] ?? null),
                'local_authority_district_code_2024' => $this->normalize($data['local authority district code (2024)'] ?? null),
                'local_authority_district_name_2024' => $this->normalize($data['local authority district name (2024)'] ?? null),
                'imd_rank' => $this->normalize($data['index of multiple deprivation (imd) rank (where 1 is most deprived)'] ?? null),
                'imd_decile' => $this->normalize($data['index of multiple deprivation (imd) decile (where 1 is most deprived 10% of lsoas)'] ?? null),
                'imd_quantile_2025' => $this->normalizeInt($data['imd_quantile_2025'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $processed++;

            if (count($payload) >= 1000) {
                ImdLsoa25::insert($payload);
                $payload = [];
            }
        }

        if ($payload !== []) {
            ImdLsoa25::insert($payload);
        }

        if ($processed > 0) {
            Import::whereKey($this->importId)->increment('processed_rows', $processed);
        }

        if ($failed > 0) {
            Import::whereKey($this->importId)->increment('failed_rows', $failed);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isEmptyRow(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
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