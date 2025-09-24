<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Cargo</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

<div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-2xl">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Edit Cargo Record</h2>

    <form action="{{ route('cargo.update', $cargo->id) }}" method="POST" class="grid grid-cols-2 gap-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-semibold mb-2">Cargo No</label>
            <input type="text" name="cargo_no" value="{{ old('cargo_no', $cargo->cargo_no) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2">Cargo Type</label>
            <input type="text" name="cargo_type" value="{{ old('cargo_type', $cargo->cargo_type) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2">Cargo Size</label>
            <input type="text" name="cargo_size" value="{{ old('cargo_size', $cargo->cargo_size) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2">Weight (kg)</label>
            <input type="number"  name="weight" value="{{ old('weight', $cargo->weight) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div class="col-span-2">
            <label class="block text-sm font-semibold mb-2">Remarks</label>
            <input type="text" name="remarks" value="{{ old('remarks', $cargo->remarks) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2">Wharfage</label>
            <input type="number" step="0.01" name="wharfage" value="{{ old('wharfage', $cargo->wharfage) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2">Penalty Days</label>
            <input type="number" name="penalty_days" value="{{ old('penalty_days', $cargo->penalty_days) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2">Storage</label>
            <input type="number" step="0.01" name="storage" value="{{ old('storage', $cargo->storage) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2">Electricity</label>
            <input type="number" step="0.01" name="electricity" value="{{ old('electricity', $cargo->electricity) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2">Destuffing</label>
            <input type="number" step="0.01" name="destuffing" value="{{ old('destuffing', $cargo->destuffing) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2">Lifting</label>
            <input type="number" step="0.01" name="lifting" value="{{ old('lifting', $cargo->lifting) }}" 
                   class="w-full border px-3 py-2 rounded">
        </div>

        {{-- Submit + Cancel --}}
        <div class="col-span-2 flex justify-end space-x-2 mt-6">
            <a href="{{ route('cargo.index') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded">
               Cancel
            </a>
            <button type="submit" 
                    class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded">
                Update
            </button>
        </div>
    </form>
</div>

</body>
</html>
