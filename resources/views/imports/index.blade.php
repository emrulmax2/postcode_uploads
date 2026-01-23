<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Excel Imports') }}
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
                <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="space-y-4">
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
                <div
                    id="imports-status"
                    data-imports='@json($imports->items())'
                    data-poll-interval="5000"
                ></div>

                <div class="mt-4">
                    {{ $imports->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
