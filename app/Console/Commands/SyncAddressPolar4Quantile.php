<?php

namespace App\Console\Commands;

use App\Models\PostcodeRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncAddressPolar4Quantile extends Command
{
    protected $signature = 'addresses:sync-polar4
        {--connection=secondary : The secondary DB connection name}
        {--table=addresses : The address table name}
        {--post-code=post_code : The address table postcode column}
        {--target=polar_4_quantile : The address table target column}
        {--chunk=1000 : Chunk size for processing postcode records}
        {--dry-run : Show what would be updated without writing}';

    protected $description = 'Update the address table polar_4_quantile based on postcode_records (postcode/postcode2).';

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $table = (string) $this->option('table');
        $postCodeColumn = (string) $this->option('post-code');
        $targetColumn = (string) $this->option('target');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        if (!$this->isSafeIdentifier($table) || !$this->isSafeIdentifier($postCodeColumn) || !$this->isSafeIdentifier($targetColumn)) {
            $this->error('Unsafe table or column name provided.');
            return 1;
        }

        $this->info('Starting sync...');

        $updated = 0;
        $scanned = 0;

        PostcodeRecord::query()
            ->select(['id', 'postcode', 'postcode2', 'polar4_quintile'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($records) use (
                $connection,
                $table,
                $postCodeColumn,
                $targetColumn,
                $dryRun,
                &$updated,
                &$scanned
            ) {
                $map = [];

                foreach ($records as $record) {
                    $scanned++;

                    $value = $record->polar4_quintile;
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $this->addMapping($map, $record->postcode, $value);
                    $this->addMapping($map, $record->postcode2, $value);
                }

                if ($map === []) {
                    return;
                }

                $keys = array_keys($map);
                $caseSql = implode(' ', array_fill(0, count($keys), 'WHEN ? THEN ?'));
                $inSql = implode(',', array_fill(0, count($keys), '?'));

                $sql = "UPDATE {$table} "
                    . "SET {$targetColumn} = CASE REPLACE(UPPER({$postCodeColumn}), ' ', '') {$caseSql} "
                    . "ELSE {$targetColumn} END "
                    . "WHERE REPLACE(UPPER({$postCodeColumn}), ' ', '') IN ({$inSql})";

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

        $this->info("Scanned {$scanned} postcode records.");
        $this->info($dryRun ? "Would update approx. {$updated} addresses." : "Updated {$updated} addresses.");

        return 0;
    }

    /**
     * @param array<string, string> $map
     */
    private function addMapping(array &$map, ?string $postcode, string $value): void
    {
        $normalized = $this->normalizePostcode($postcode);
        if ($normalized === null) {
            return;
        }

        $map[$normalized] = $value;
    }

    private function normalizePostcode(?string $postcode): ?string
    {
        if ($postcode === null) {
            return null;
        }

        $normalized = strtoupper(trim($postcode));
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        return $normalized === '' ? null : $normalized;
    }

    private function isSafeIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\.]+$/', $value);
    }
}
