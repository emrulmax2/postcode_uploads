<?php

use App\Imports\PostcodeCsvImport;
use App\Models\Import;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('imports:csv {path} {--user=}', function () {
    $path = $this->argument('path');
    $userId = $this->option('user');

    if (!is_string($path) || !file_exists($path)) {
        $this->error('File not found: '.$path);
        return 1;
    }

    if (!$userId) {
        $userId = User::query()->value('id');
    }

    if (!$userId) {
        $this->error('No users found. Register first or pass --user=ID.');
        return 1;
    }

    $storedPath = Storage::putFile('imports', new File($path));

    $import = Import::create([
        'user_id' => $userId,
        'original_name' => basename($path),
        'stored_path' => $storedPath,
        'status' => 'queued',
    ]);

    Excel::queueImport(new PostcodeCsvImport($import->id), $storedPath);

    $this->info('Queued import #'.$import->id.' for user '.$userId.'.');

    return 0;
})->purpose('Queue a CSV import from a local file path');

Schedule::command('addresses:sync-polar4')->weeklyOn(1, '02:00');
Schedule::command('addresses:sync-imd-lsoa25')->weeklyOn(1, '02:30');
