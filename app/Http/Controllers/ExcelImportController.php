<?php

namespace App\Http\Controllers;

use App\Imports\PostcodeCsvImport;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
//excellent works
class ExcelImportController extends Controller
{
    public function index(Request $request): View
    {
        $imports = Import::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('imports.index', compact('imports'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,zip', 'max:51200'],
        ]);

        $file = $validated['file'];
        $path = $file->store('imports');

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
                    ->route('imports.index')
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
                    ->route('imports.index')
                    ->withErrors(['file' => 'No CSV file was found in the uploaded zip.']);
            }

            $import->update([
                'stored_path' => $csvRelativePath,
            ]);

            Excel::queueImport(new PostcodeCsvImport($import->id), $csvRelativePath, 'local');
        } else {
            Excel::queueImport(new PostcodeCsvImport($import->id), $path);
        }

        return redirect()
            ->route('imports.index')
            ->with('status', 'Import queued. Start the queue worker to process the file.');
    }

    private function findFirstCsvPath(string $extractDir): ?string
    {
        $absoluteExtractPath = Storage::disk('local')->path($extractDir);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteExtractPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && Str::endsWith(Str::lower($file->getFilename()), '.csv')) {
                $relative = Str::after($file->getPathname(), Storage::disk('local')->path('') . DIRECTORY_SEPARATOR);
                return str_replace('\\', '/', $relative);
            }
        }

        return null;
    }

    public function show(Request $request, Import $import): Response
    {
        if ($import->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'failed_rows' => $import->failed_rows,
            'error' => $import->error,
            'created_at' => $import->created_at,
            'updated_at' => $import->updated_at,
        ]);
    }
}
