<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\PostcodeRecord;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPostcodeCsvChunk implements ShouldQueue
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

            $processed++;

            if (count($payload) >= 1000) {
                PostcodeRecord::insert($payload);
                $payload = [];
            }
        }

        if ($payload !== []) {
            PostcodeRecord::insert($payload);
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
}
