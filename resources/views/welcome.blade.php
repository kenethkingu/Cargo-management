<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cargo Management</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-200 flex flex-col items-center min-h-screen p-4">

<div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-5xl mx-auto">

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

    <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4 mb-6">
<form id="uploadForm" action="{{ route('cargo.import') }}" method="POST" enctype="multipart/form-data" class="flex items-center w-full max-w-md space-x-2">
    @csrf
    <div class="flex w-full border border-gray-400 rounded-md overflow-hidden bg-white">
        <!-- Text input to show file name -->
        <input type="text" id="file-name" placeholder="Select your Excel file" 
               readonly 
               class="flex-1 px-3 py-2 text-gray-600 cursor-pointer"
               onclick="document.getElementById('file-upload').click();">

        <!-- Blue search icon button -->
        <button type="button" onclick="document.getElementById('file-upload').click();" 
                class="bg-blue-500 hover:bg-blue-600 text-white px-4 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
            </svg>
        </button>

        <!-- Hidden file input -->
        <input type="file" name="file" id="file-upload" class="hidden" >
    </div>

            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded-md">
                Upload
            </button>
        </form>

        {{-- View Data Button --}}
        <form action="{{ route('cargo.index') }}" method="GET">
            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded-md">
                View 
            </button>
        </form>
        
    </div>

    {{-- Display Table --}}
    @if(isset($data) && $data->isNotEmpty())
        <div class="overflow-x-auto w-full">
            <table class="min-w-full bg-white border border-gray-300 shadow-md rounded-lg">
                <thead class="bg-gray-100">
                    <tr>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('file-upload');
    const fileNameField = document.getElementById('file-name');
    const uploadForm = document.getElementById('uploadForm');

    // Show selected file name
    fileInput.addEventListener('change', function(){
        if(this.files && this.files.length > 0){
            fileNameField.value = this.files[0].name;
        } else {
            fileNameField.value = '';
        }
    });

    // Front-end validation before submitting
    uploadForm.addEventListener('submit', function(e){
        if(!fileInput.files || fileInput.files.length === 0){
            e.preventDefault();
            alert('No file selected! Please select an Excel file before uploading.');
        }
    });
});
</script>
</body>
</html>
