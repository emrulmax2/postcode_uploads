<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('IMD LSOA25 Imports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-100 text-green-700 rounded">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 bg-red-100 text-red-700 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('imd-lsoa25.imports.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="file">Excel file (xlsx, xls, csv, zip)</label>
                        <input type="file" name="file" id="file" required class="mt-1 block w-full border-gray-300 rounded" />
                    </div>
                    <div class="flex items-center gap-4">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Upload & Queue Import</button>
                        <span class="text-sm text-gray-500">Run the queue worker to process large files.</span>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Recent Imports</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2">File</th>
                                <th class="py-2">Status</th>
                                <th class="py-2">Progress</th>
                                <th class="py-2">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($imports as $import)
                                @php
                                    $percent = $import->total_rows
                                        ? min(100, (int) round(($import->processed_rows / max(1, $import->total_rows)) * 100))
                                        : 0;
                                @endphp
                                <tr class="border-b import-row" data-import-id="{{ $import->id }}" data-show-url="{{ route('imd-lsoa25.imports.show', $import) }}">
                                    <td class="py-2">{{ $import->original_name }}</td>
                                    <td class="py-2 capitalize import-status">{{ $import->status }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-3">
                                            <div class="w-40 bg-gray-200 rounded h-2 overflow-hidden">
                                                <div class="h-2 bg-indigo-600 import-progress-bar" style="width: {{ $percent }}%"></div>
                                            </div>
                                            <div class="text-xs text-gray-600 whitespace-nowrap">
                                                <span class="import-progress">{{ $percent }}</span>%
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <span class="import-processed">{{ $import->processed_rows }}</span>
                                            <span class="import-total">
                                                @if ($import->total_rows)
                                                    / {{ $import->total_rows }}
                                                @else
                                                    / --
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 import-updated">{{ $import->updated_at->diffForHumans() }}</td>
                                </tr>
                                <tr class="border-b bg-red-50 import-error-row" @if(!$import->error) style="display: none;" @endif>
                                    <td colspan="4" class="py-2 text-red-600 import-error">{{ $import->error }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-gray-500">No imports yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $imports->links() }}
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = Array.from(document.querySelectorAll('.import-row'));

            if (rows.length === 0) {
                return;
            }

            const pollInterval = 5000;

            const updateRow = (row, data) => {
                const statusEl = row.querySelector('.import-status');
                const progressEl = row.querySelector('.import-progress');
                const progressBarEl = row.querySelector('.import-progress-bar');
                const processedEl = row.querySelector('.import-processed');
                const totalEl = row.querySelector('.import-total');
                const updatedEl = row.querySelector('.import-updated');
                const errorRow = row.nextElementSibling;
                const errorEl = errorRow?.classList.contains('import-error-row') ? errorRow.querySelector('.import-error') : null;

                if (statusEl) {
                    statusEl.textContent = data.status ?? 'queued';
                }

                if (processedEl) {
                    processedEl.textContent = data.processed_rows ?? 0;
                }

                if (totalEl) {
                    const totalRows = data.total_rows ?? null;
                    totalEl.textContent = totalRows ? `/ ${totalRows}` : '/ --';
                }

                const percent = data.batch?.progress ?? (
                    data.total_rows ? Math.min(100, Math.round((data.processed_rows / Math.max(1, data.total_rows)) * 100)) : 0
                );

                if (progressEl) {
                    progressEl.textContent = percent;
                }

                if (progressBarEl) {
                    progressBarEl.style.width = `${percent}%`;
                }

                if (updatedEl && data.updated_at) {
                    updatedEl.textContent = new Date(data.updated_at).toLocaleString();
                }

                if (errorRow && errorEl) {
                    if (data.error) {
                        errorEl.textContent = data.error;
                        errorRow.style.display = '';
                    } else {
                        errorRow.style.display = 'none';
                    }
                }
            };

            const poll = async () => {
                await Promise.all(rows.map(async (row) => {
                    const url = row.dataset.showUrl;

                    if (!url) {
                        return;
                    }

                    try {
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        if (!response.ok) {
                            return;
                        }

                        const data = await response.json();
                        updateRow(row, data);
                    } catch (error) {
                        // ignore polling errors
                    }
                }));
            };

            poll();
            setInterval(poll, pollInterval);
        });
    </script>
</x-app-layout>