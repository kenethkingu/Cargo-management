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

        {{-- Blue Search Icon: Select Excel --}}
        <form action="{{ route('cargo.import') }}" method="POST" enctype="multipart/form-data" class="flex items-center space-x-2">
            @csrf
            <input type="file" name="file" class="border border-gray-400 rounded-md p-2" required>
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
</body>
</html>
