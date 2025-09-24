<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"> 
<head>
    <meta charset="utf-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cargo Management</title>

    <!-- Laravel Vite: loads your compiled CSS and JS -->
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
    @vite('resources/js/import-progress.js')

    <!-- Toastify library for notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
   
</head>
<body class="bg-gray-200 flex flex-col items-center min-h-screen p-4">

<div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-5xl mx-auto"> <!-- Main container -->

    {{-- Flash Messages --}}
    @if(session('success')) 
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    <!-- Upload + View Buttons -->
    <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4 mb-6">

        <!-- Upload Form -->
        <form id="uploadForm" action="{{ route('cargo.import') }}" method="POST" enctype="multipart/form-data" class="flex items-center w-full max-w-md space-x-2">
            @csrf

            <!-- File input with custom UI -->
            <div class="flex w-full border border-gray-400 rounded-md overflow-hidden bg-white">
                <!-- Text input showing file name -->
                <input type="text" id="file-name" placeholder="Select your Excel file" 
                       readonly 
                       class="flex-1 px-3 py-2 text-gray-600 cursor-pointer"
                       onclick="document.getElementById('file-upload').click();">

                <!-- Blue search icon button (click triggers file upload) -->
                <button type="button" onclick="document.getElementById('file-upload').click();" 
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 flex items-center justify-center">
                    <!-- Search icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                </button>

                <!-- Hidden file input -->
                <input type="file" name="file" id="file-upload" class="hidden" >
            </div>

            <!-- Show validation error for file -->
            @error('file')
                <div class="text-red-500 text-sm mt-2">{{ $message }}</div>
            @enderror

            <!-- Upload button -->
            <button type="submit" class="bg-green-500 hover:bg-green-600 text-black-200 font-bold py-2 px-6 rounded-md">
                Upload
            </button>
        </form>

        {{-- View Data Button --}}
        <form action="{{ route('cargo.index') }}" method="GET">
            <button type="submit" class="bg-green-500 hover:bg-green-600 text-black-200 font-bold py-2 px-6 rounded-md">
                View Data
            </button>
        </form>
        
    </div>
    
    {{-- Global Progress Bar --}}
    <div id="progress-container" class="hidden mb-6 p-4 bg-gray-50 rounded-lg border">
        <h3 class="text-lg font-semibold mb-3 text-gray-800">Import Progress</h3>
        
        <!-- Progress text -->
        <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium text-gray-700">Overall Progress</span>
            <span class="text-sm text-gray-600" id="global-progress-text">0%</span>
        </div>

        <!-- Progress bar -->
        <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
            <div id="progress-bar-global" class="bg-blue-500 h-4 rounded-full text-xs text-white flex items-center justify-center transition-all duration-500 ease-out" style="width: 0%">
                0%
            </div>
        </div>

        <!-- Details about progress -->
        <div class="text-xs text-gray-500 mt-1" id="progress-details">Waiting for upload to start...</div>
    </div>

    {{-- Recent Import Batches --}}
    @php
        $recentBatches = \App\Models\ImportBatch::latest()->limit(5)->get(); // Fetch last 5 batches
    @endphp

    @if($recentBatches->count() > 0)
    <div class="mb-6">
        <h3 class="text-lg font-semibold mb-3 text-gray-800">Recent Imports</h3>
        <div class="space-y-3" id="batch-progress-container">

            <!-- Loop through batches -->
            @foreach($recentBatches as $batch)
            <div class="batch-progress border border-gray-300 rounded-lg p-4 bg-white shadow-sm hover:shadow-md transition-shadow duration-200" id="batch-{{ $batch->id }}">
                
                <!-- Header: batch ID + status -->
                <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center space-x-3">
                        <span class="font-medium text-gray-900">Import #{{ $batch->id }}</span>
                        <!-- Status badge -->
                        <span class="text-xs px-2 py-1 rounded-full 
                            @if($batch->status === 'completed') bg-green-100 text-green-800 border border-green-200
                            @elseif($batch->status === 'failed') bg-red-100 text-red-800 border border-red-200
                            @elseif($batch->status === 'processing') bg-blue-100 text-blue-800 border border-blue-200
                            @else bg-yellow-100 text-yellow-800 border border-yellow-200 @endif" 
                            id="status-{{ $batch->id }}">
                            {{ ucfirst($batch->status ?? 'pending') }}
                        </span>
                    </div>
                    <span class="text-xs text-gray-500">{{ $batch->created_at->format('M j, H:i') }}</span>
                </div>
                
                <!-- File name -->
                <div class="text-sm text-gray-600 mb-2">
                    <span class="font-medium">File:</span> {{ $batch->file_name }}
                </div>
                
                <!-- Progress text -->
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Progress: 
                        <span id="processed-{{ $batch->id }}">{{ $batch->processed_rows ?? 0 }}</span>/
                        <span id="total-{{ $batch->id }}">{{ $batch->total_rows ?? 0 }}</span> rows
                    </span>
                    <span id="progress-text-{{ $batch->id }}" class="font-medium">
                        {{ $batch->total_rows > 0 ? round(($batch->processed_rows / $batch->total_rows) * 100, 1) : 0 }}%
                    </span>
                </div>
                
                <!-- Progress bar -->
                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-1">
                    <div id="progress-bar-{{ $batch->id }}" 
                         class="h-2.5 rounded-full transition-all duration-500 ease-out
                         @if($batch->status === 'completed') bg-green-500
                         @elseif($batch->status === 'failed') bg-red-500
                         @elseif($batch->status === 'processing') bg-blue-500
                         @else bg-yellow-500 @endif" 
                         style="width: {{ $batch->total_rows > 0 ? min(100, ($batch->processed_rows / $batch->total_rows) * 100) : 0 }}%">
                    </div>
                </div>
                
                <!-- Optional message -->
                @if($batch->message)
                <div class="text-xs text-gray-500 mt-2">
                    <span class="font-medium">Message:</span> {{ $batch->message }}
                </div>
                @endif
                
                <!-- Completion timestamp -->
                @if($batch->completed_at)
                <div class="text-xs text-gray-400 mt-1">
                    Completed: {{ $batch->completed_at->format('M j, H:i') }}
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Display Table --}}
    @if(isset($data) && $data->isNotEmpty()) <!-- Show table only if cargo data exists -->
        <div class="overflow-x-auto w-full">
            <table class="min-w-full bg-white border border-gray-300 shadow-md rounded-lg">
                <thead class="bg-gray-100">
                    <tr>
                        <!-- Table headers -->
                        <th class="py-3 px-4 text-left">Cargo No</th>
                        <th class="py-3 px-4 text-left">Cargo Type</th>
                        <th class="py-3 px-4 text-left">Cargo Size</th>
                        <th class="py-3 px-4 text-left">Weight</th>
                        <th class="py-3 px-4 text-left">Remarks</th>
                        <th class="py-3 px-4 text-left">Wharfage</th>
                        <th class="py-3 px-4 text-left">Penalty</th>
                        <th class="py-3 px-4 text-left">Storage</th>
                        <th class="py-3 px-4 text-left">Electricity</th>
                        <th class="py-3 px-4 text-left">Destuffing</th>
                        <th class="py-3 px-4 text-left">Lifting</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loop cargo rows -->
                    @foreach($data as $cargo)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-3 px-4">{{ $cargo->cargo_no }}</td>
                            <td class="py-3 px-4">{{ $cargo->cargo_type }}</td>
                            <td class="py-3 px-4">{{ $cargo->cargo_size }}</td>
                            <td class="py-3 px-4">{{ $cargo->weight }}</td>
                            <td class="py-3 px-4">{{ $cargo->remarks }}</td>
                            <td class="py-3 px-4">{{ number_format($cargo->wharfage,2) }}</td>
                            <td class="py-3 px-4">{{ $cargo->penalty_days }}</td>
                            <td class="py-3 px-4">{{ number_format($cargo->storage,2) }}</td>
                            <td class="py-3 px-4">{{ number_format($cargo->electricity,2) }}</td>
                            <td class="py-3 px-4">{{ $cargo->destuffing }}</td>
                            <td class="py-3 px-4">{{ number_format($cargo->lifting,2) }}</td>

                            <!-- Edit & Delete buttons -->
                            <td class="py-3 px-4 flex space-x-2">
                                <a href="{{ route('cargo.edit', $cargo->id) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded text-xs">Edit</a>
                                <form action="{{ route('cargo.destroy', $cargo->id) }}" method="POST" onsubmit="return confirm('Are you sure?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $data->links() }} 
            </div>
        </div>
    @endif

</div>
<script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js"></script>
<!-- JS: pass batch IDs to JS script -->
<script>
    window.importBatchIds = [@foreach($recentBatches as $b){{ $b->id }}@if(!$loop->last),@endif @endforeach];
    console.log('Batch IDs loaded:', window.importBatchIds);
</script>
</body>
</html>
