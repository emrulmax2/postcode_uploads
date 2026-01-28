<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImdLsoa25CsvChunk;
use App\Models\Import;
use Illuminate\Bus\Batch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ImdLsoa25ImportController extends Controller
{
    public function index(Request $request): View
    {
        $imports = Import::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('imd_lsoa25_imports.index', compact('imports'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,zip', 'max:51200'],
        ]);

        $file = $validated['file'];
        $path = $file->store('imports');
        $absolutePath = Storage::disk('local')->path($path);

        $import = Import::create([
            'user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'status' => 'queued',
        ]);

        if (Str::lower($file->getClientOriginalExtension()) === 'zip') {
            $zipPath = Storage::disk('local')->path($path);
            $extractDir = 'imports/extracted/' . $import->id;
            Storage::disk('local')->makeDirectory($extractDir);

            $zip = new \ZipArchive();

            if ($zip->open($zipPath) !== true) {
                $import->update([
                    'status' => 'failed',
                    'error' => 'Unable to open the zip archive.',
                ]);

                return redirect()
                    ->route('imd-lsoa25.imports.index')
                    ->withErrors(['file' => 'Unable to open the zip archive.']);
            }

            $zip->extractTo(Storage::disk('local')->path($extractDir));
            $zip->close();

            $csvRelativePath = $this->findFirstCsvPath($extractDir);

            if (!$csvRelativePath) {
                $import->update([
                    'status' => 'failed',
                    'error' => 'No CSV file was found in the uploaded zip.',
                ]);

                return redirect()
                    ->route('imd-lsoa25.imports.index')
                    ->withErrors(['file' => 'No CSV file was found in the uploaded zip.']);
            }

            $diskRoot = rtrim(Storage::disk('local')->path(''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $absoluteCsvPath = Str::startsWith($csvRelativePath, [$diskRoot, DIRECTORY_SEPARATOR])
                ? $csvRelativePath
                : Storage::disk('local')->path($csvRelativePath);

            if (!file_exists($absoluteCsvPath)) {
                $import->update([
                    'status' => 'failed',
                    'error' => 'CSV file was not found after extraction.',
                ]);

                return redirect()
                    ->route('imd-lsoa25.imports.index')
                    ->withErrors(['file' => 'CSV file was not found after extraction.']);
            }

            $import->update([
                'stored_path' => $csvRelativePath,
            ]);

            $this->dispatchBatchImport($import, $absoluteCsvPath);
        } else {
            $this->dispatchBatchImport($import, $absolutePath);
        }

        return redirect()
            ->route('imd-lsoa25.imports.index')
            ->with('status', 'Import queued. Start the queue worker to process the file.');
    }

    private function findFirstCsvPath(string $extractDir): ?string
    {
        $absoluteExtractPath = Storage::disk('local')->path($extractDir);
        $diskRoot = rtrim(Storage::disk('local')->path(''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteExtractPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && Str::endsWith(Str::lower($file->getFilename()), '.csv')) {
                $pathname = $file->getPathname();
                if (Str::startsWith($pathname, $diskRoot)) {
                    $relative = substr($pathname, strlen($diskRoot));
                    return str_replace('\\', '/', $relative);
                }

                return str_replace('\\', '/', $pathname);
            }
        }

        return null;
    }

    private function dispatchBatchImport(Import $import, string $absoluteCsvPath): void
    {
        [$header, $totalRows] = $this->getCsvHeaderAndRowCount($absoluteCsvPath);

        if ($totalRows === 0) {
            $import->update([
                'status' => 'failed',
                'error' => 'The CSV file is empty or contains no data rows.',
            ]);

            return;
        }

        $jobs = $this->buildChunkJobs($import->id, $absoluteCsvPath, $header, $totalRows);

        $batch = Bus::batch($jobs)
            ->name("IMD LSOA25 Import #{$import->id}")
            ->then(function () use ($import): void {
                $import->update([
                    'status' => 'completed',
                ]);
            })
            ->catch(function (Batch $batch, Throwable $exception) use ($import): void {
                $import->update([
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ]);
            })
            ->dispatch();

        $import->update([
            'status' => 'processing',
            'total_rows' => $totalRows,
            'batch_id' => $batch->id,
        ]);
    }

    /**
     * @return array{0: array<int, string>, 1: int}
     */
    private function getCsvHeaderAndRowCount(string $absoluteCsvPath): array
    {
        $file = new \SplFileObject($absoluteCsvPath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $header = $file->fgetcsv();

        if ($header === false || $header === [null]) {
            return [[], 0];
        }

        $header = array_map(static function ($value) {
            $value = $value === null ? '' : (string) $value;
            $value = trim($value);

            return $value;
        }, $header);

        if ($header !== [] && $header[0] !== null) {
            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
        }

        $rowCount = 0;

        while (!$file->eof()) {
            $row = $file->fgetcsv();

            if ($row === false || $row === [null] || $this->isEmptyCsvRow($row)) {
                continue;
            }

            $rowCount++;
        }

        return [$header, $rowCount];
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $header
     * @return array<int, ProcessImdLsoa25CsvChunk>
     */
    private function buildChunkJobs(int $importId, string $absoluteCsvPath, array $header, int $totalRows): array
    {
        $chunkSize = 5000;
        $jobs = [];

        for ($offset = 0; $offset < $totalRows; $offset += $chunkSize) {
            $startLine = 1 + $offset;
            $endLine = min($totalRows, $offset + $chunkSize);

            $jobs[] = new ProcessImdLsoa25CsvChunk(
                $importId,
                $absoluteCsvPath,
                $header,
                $startLine,
                $endLine
            );
        }

        return $jobs;
    }

    public function show(Request $request, Import $import): Response
    {
        if ($import->user_id !== $request->user()->id) {
            abort(403);
        }

        $batch = $import->batch_id ? Bus::findBatch($import->batch_id) : null;

        $batchSummary = $batch
            ? [
                'id' => $batch->id,
                'total_jobs' => $batch->totalJobs,
                'pending_jobs' => $batch->pendingJobs,
                'processed_jobs' => $batch->processedJobs(),
                'failed_jobs' => $batch->failedJobs,
                'progress' => $batch->progress(),
                'finished_at' => $batch->finishedAt?->toDateTimeString(),
                'cancelled' => $batch->cancelled(),
            ]
            : null;

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'failed_rows' => $import->failed_rows,
            'error' => $import->error,
            'batch' => $batchSummary,
            'created_at' => $import->created_at,
            'updated_at' => $import->updated_at,
        ]);
    }
}