<?php

namespace App\Http\Controllers;

use App\Jobs\ImportCargoJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Cargo;
use App\Models\ImportBatch;
use Illuminate\Validation\ValidationException;


class CargoController extends Controller
{
    

    /**
     * Show the upload page
     */
    public function welcome()
    {
        $recentImports = ImportBatch::latest()
            ->take(5)
            ->get(['id', 'file_name', 'status', 'progress', 'created_at', 'message']);
        
            
        return view('welcome', compact('recentImports'));
    }

    /**
     * Display all cargos (View Data page)
     */
    public function index(Request $request)
    {
        // Method to display a paginated list of cargo records with search, filter, and sort functionality
        $query = Cargo::query();

        // Add search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('cargo_no', 'LIKE', "%{$search}%")
                  ->orWhere('cargo_type', 'LIKE', "%{$search}%")
                  ->orWhere('remarks', 'LIKE', "%{$search}%");
            });
        }

        // Add filtering by cargo type
        if ($request->filled('cargo_type')) {
            $query->where('cargo_type', $request->cargo_type);
        }
        
        $sortField = $request->get('sort', 'id');
        $sortDirection = $request->get('direction', 'asc');
        // Gets sort field and direction from request, defaulting to 'id' and 'asc'
        
        $allowedSortFields = ['id', 'cargo_no', 'cargo_type', 'cargo_size', 'weight', 'created_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('id', 'asc');
        }
        // Applies sorting on allowed fields, falling back to 'id' ascending if invalid

        $data = $query->paginate(15)->withQueryString();
      
        
        // Get unique cargo types for filter dropdown
        $cargoTypes = Cargo::distinct('cargo_type')
            ->whereNotNull('cargo_type')
            ->pluck('cargo_type')
            ->sort();
        // Retrieves unique, non-null cargo types for a filter dropdown, sorted alphabetically

        return view('welcome', compact('data', 'cargoTypes'));
        // Returns the 'welcome' view, passing cargo data and cargo types
    }

    /**
     * Import cargos from Excel/CSV
     */
    public function import(Request $request)
    {
        // Method to handle Excel/CSV file uploads and dispatch import jobs
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
            ], [
                'file.required' => 'Please select a file to upload.',
                'file.file' => 'The uploaded file is invalid.',
                'file.mimes' => 'Only Excel files (.xlsx, .xls, .csv) are allowed.',
                'file.max' => 'The file size must not exceed 10MB.'
            ]);

            if (!$request->hasFile('file')) {
                throw ValidationException::withMessages([
                    'file' => 'No file was uploaded.'
                ]);
            }

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            // Gets the uploaded file and its original name
            
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['xlsx', 'xls', 'csv'];
            if (!in_array($extension, $allowedExtensions)) {
                throw ValidationException::withMessages([
                    'file' => "Invalid file type: {$extension}. Only Excel files (.xlsx, .xls, .csv) are allowed."
                ]);
            }
            // Explicitly checks the file extension, throwing a validation exception for invalid types

            // Generate unique filename
            $filename = time() . '_' . str_replace(' ', '_', $originalName);
            $filePath = $file->storeAs('imports', $filename);
            // Creates a unique filename and stores the file in the 'imports' directory

            if (!$filePath) {
                throw new \Exception('Failed to store uploaded file.');
            }
            // Throws an exception if file storage fails

            // Create import batch record
            $batch = DB::transaction(function () use ($originalName, $filePath) {
                return ImportBatch::create([
                    'file_name' => $originalName,
                    'file_path' => $filePath,
                    'status' => 'pending',
                    'processed_rows' => 0,
                    'total_rows' => 0,
                    'progress' => 0,
                    'created_at' => now(),
                ]);
            });
           
            ImportCargoJob::dispatch($batch->id, $filePath);
            // Dispatches the ImportCargoJob for asynchronous processing

            Log::info('Import job dispatched', [
                'batch_id' => $batch->id,
                'file_name' => $originalName,
                'file_path' => $filePath
            ]);
            // Logs the dispatch of the import job

            return redirect()->back()->with('success', 
                "File '{$originalName}' uploaded successfully! Import is running in the background. " .
                "Import ID: {$batch->id}"
            );

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'file_name' => $request->file('file')?->getClientOriginalName()
            ]);
            
            return redirect()->back()->with('error', 
                'File upload failed: ' . $e->getMessage()
            );
           
        }
    }

    /**
     * Show the import progress
     */
    public function importProgress($id)
    {
        // Method to return JSON data for batch import progress
        try {
            $batch = ImportBatch::findOrFail($id);
            // Retrieves the ImportBatch record by ID, throws an exception if not found
            
            $progress = 0;
            if ($batch->total_rows > 0) {
                $progress = round(($batch->processed_rows / $batch->total_rows) * 100, 2);
            } elseif ($batch->status === 'completed') {
                $progress = 100;
            }
            // Calculates progress percentage, setting to 100% for completed batches with no rows

            return response()->json([
                'id' => $batch->id,
                'progress' => $progress,
                'status' => $batch->status,
                'processed_rows' => $batch->processed_rows,
                'total_rows' => $batch->total_rows,
                'error_count' => $batch->error_count ?? 0,
                'message' => $batch->message,
                'created_at' => $batch->created_at?->format('Y-m-d H:i:s'),
                'completed_at' => $batch->completed_at?->format('Y-m-d H:i:s'),
            ]);
            // Returns JSON with batch details for frontend progress updates
            
        } catch (\Exception $e) {
            Log::error('Failed to get import progress', [
                'batch_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            
            return response()->json([
                'error' => 'Failed to retrieve import progress'
            ], 500);
            
        }
    }

    /**
     * Store new cargo
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'cargo_no'      => 'required|string|max:255|unique:cargos,cargo_no',
                'cargo_type'    => 'required|string|max:100',
                'cargo_size'    => 'required|integer|min:1|max:50',
                'weight'        => 'nullable|numeric|min:0',
                'remarks'       => 'nullable|string|max:1000',
                'wharfage'      => 'nullable|numeric|min:0',
                'penalty_days'  => 'nullable|integer|min:0',
                'storage'       => 'nullable|numeric|min:0',
                'electricity'   => 'nullable|numeric|min:0',
                'destuffing'    => 'nullable|numeric|min:0',
                'lifting'       => 'nullable|numeric|min:0',
            ]);
            
            Cargo::create($validatedData);
            return redirect()->route('cargo.index')->with('success', 'Cargo added successfully!');
            
            
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create cargo', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            
            return redirect()->back()->with('error', 'Failed to add cargo: ' . $e->getMessage());
           
        }
    }

    /**
     * Show the edit form
     */
    public function edit($id)
    {
        
        try {
            $cargo = Cargo::findOrFail($id);
           
            return view('cargo.edit', compact('cargo'));
            
        } catch (\Exception $e) {
            return redirect()->route('cargo.welcome')->with('error', 'Cargo not found.');
        }
    }

    /**
     * Update an existing cargo
     */
    public function update(Request $request, $id)
    {
        
        try {
            $cargo = Cargo::findOrFail($id);
            $validatedData = $request->validate([
                'cargo_no'      => 'required|string|max:255|unique:cargos,cargo_no,' . $id,
                'cargo_type'    => 'required|string|max:100',
                'cargo_size'    => 'required|integer|min:1|max:50',
                'weight'        => 'nullable|numeric|min:0',
                'remarks'       => 'nullable|string|max:1000',
                'wharfage'      => 'nullable|numeric|min:0',
                'penalty_days'  => 'nullable|integer|min:0',
                'storage'       => 'nullable|numeric|min:0',
                'electricity'   => 'nullable|numeric|min:0',
                'destuffing'    => 'nullable|numeric|min:0',
                'lifting'       => 'nullable|numeric|min:0',
            ]);
            

            $cargo->update($validatedData);
            return redirect()->route('cargo.index')->with('success', 'Cargo updated successfully.');
            
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update cargo', [
                'cargo_id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return redirect()->back()->with('error', 'Failed to update cargo: ' . $e->getMessage());
        }
    }

    /**
     * Delete a cargo
     */
    public function destroy(Cargo $cargo)
    {
        try {
            $cargo->delete();
            return redirect()->route('cargo.index')->with('success', 'Cargo deleted successfully!');
            
        } catch (\Exception $e) {
            Log::error('Failed to delete cargo', [
                'cargo_id' => $cargo->id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('cargo.index')->with('error', 'Failed to delete cargo.');
        }
    }
}