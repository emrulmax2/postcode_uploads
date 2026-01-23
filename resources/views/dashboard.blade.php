<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="space-y-4">
                        <div>{{ __("You're logged in!") }}</div>
                        <a href="{{ route('imports.index') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded">
                            Go to Excel Imports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
