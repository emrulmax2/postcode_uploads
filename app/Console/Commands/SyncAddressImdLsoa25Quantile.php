<?php

namespace App\Console\Commands;

use App\Models\ImdLsoa25;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncAddressImdLsoa25Quantile extends Command
{
    protected $signature = 'addresses:sync-imd-lsoa25
        {--connection=secondary : The secondary DB connection name}
        {--table=addresses : The address table name}
        {--lsoa=lsoa_21 : The address table LSOA column}
        {--target=imd_quantile_2025 : The address table target column}
        {--chunk=1000 : Chunk size for processing imd_lsoa25 records}
        {--dry-run : Show what would be updated without writing}';

    protected $description = 'Update the address table imd_quantile_2025 based on imd_lsoa25 (lsoa_code_2021).';

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $table = (string) $this->option('table');
        $lsoaColumn = (string) $this->option('lsoa');
        $targetColumn = (string) $this->option('target');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        if (!$this->isSafeIdentifier($table) || !$this->isSafeIdentifier($lsoaColumn) || !$this->isSafeIdentifier($targetColumn)) {
            $this->error('Unsafe table or column name provided.');
            return 1;
        }

        $this->info('Starting IMD LSOA25 sync...');

        $updated = 0;
        $scanned = 0;

        ImdLsoa25::query()
            ->select(['id', 'lsoa_code_2021', 'imd_quantile_2025'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($records) use (
                $connection,
                $table,
                $lsoaColumn,
                $targetColumn,
                $dryRun,
                &$updated,
                &$scanned
            ) {
                $map = [];

                foreach ($records as $record) {
                    $scanned++;

                    $value = $record->imd_quantile_2025;
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $this->addMapping($map, $record->lsoa_code_2021, (string) $value);
                }

                if ($map === []) {
                    return;
                }

                $keys = array_keys($map);
                $caseSql = implode(' ', array_fill(0, count($keys), 'WHEN ? THEN ?'));
                $inSql = implode(',', array_fill(0, count($keys), '?'));

                $sql = "UPDATE {$table} "
                    . "SET {$targetColumn} = CASE REPLACE(UPPER({$lsoaColumn}), ' ', '') {$caseSql} "
                    . "ELSE {$targetColumn} END "
                    . "WHERE REPLACE(UPPER({$lsoaColumn}), ' ', '') IN ({$inSql})";

                $bindings = [];
                foreach ($map as $key => $value) {
                    $bindings[] = $key;
                    $bindings[] = $value;
                }
                $bindings = array_merge($bindings, $keys);

                if ($dryRun) {
                    $updated += count($map);
                    return;
                }

                $affected = DB::connection($connection)->update($sql, $bindings);
                $updated += $affected;
            });

        $this->info("Scanned {$scanned} IMD LSOA25 records.");
        $this->info($dryRun ? "Would update approx. {$updated} addresses." : "Updated {$updated} addresses.");

        return 0;
    }

    /**
     * @param array<string, string> $map
     */
    private function addMapping(array &$map, ?string $lsoa, string $value): void
    {
        $normalized = $this->normalizeLsoa($lsoa);
        if ($normalized === null) {
            return;
        }

        $map[$normalized] = $value;
    }

    private function normalizeLsoa(?string $lsoa): ?string
    {
        if ($lsoa === null) {
            return null;
        }

        $normalized = strtoupper(trim($lsoa));
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        return $normalized === '' ? null : $normalized;
    }

    private function isSafeIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\.]+$/', $value);
    }
}