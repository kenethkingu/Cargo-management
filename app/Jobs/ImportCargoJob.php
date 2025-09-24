<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CargoImport;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Validators\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;


class ImportCargoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $batchId;
    public $filePath;
    public $timeout = 3600; // 1 hour timeout
    public $tries = 3;
    public $maxExceptions = 1;

    public function __construct(int $batchId, string $filePath)
    {
        $this->batchId = $batchId;
        $this->filePath = $filePath;
        $this->onQueue('imports');
        // Assigns the job to the 'imports' queue for processing
    }

    public function middleware()
    {
        return [
            new WithoutOverlapping($this->batchId)
        ];
        // Uses WithoutOverlapping middleware to prevent concurrent processing of the same batch
    }

    public function handle()
    {
        $batch = ImportBatch::findOrFail($this->batchId);
        // Retrieves the ImportBatch record by ID, throws an exception if not found
        $fullPath = Storage::path($this->filePath);
        // Converts the storage file path to a full system path

        Log::info('Starting import job', [
            'batch_id' => $this->batchId,
            'file_path' => $this->filePath,
            'full_path' => $fullPath
        ]);
        // Logs the start of the import job with batch and file details

        try {
            if (!Storage::exists($this->filePath)) {
                throw new \Exception('Import file not found: ' . $this->filePath);
            }
            // Checks if the file exists in Laravel's storage

            if (!file_exists($fullPath)) {
                throw new \Exception('Physical file not found: ' . $fullPath);
            }
            // Verifies the file exists on the filesystem

            $this->validateFileFormat($fullPath);

            $batch->update([
                'status' => 'processing',
                'started_at' => now(),
                'message' => 'Reading file and counting rows...'
            ]);
            // Updates the batch status to 'processing' with a progress message

            // Count total rows for progress tracking
            $totalRows = $this->countTotalRows($fullPath);
            
            if ($totalRows === 0) {
                throw new \Exception('The file appears to be empty or contains no data rows.');
            }

            Log::info('File analysis complete', [
                'batch_id' => $this->batchId,
                'total_rows' => $totalRows
            ]);

            // Update batch with total row count
            $batch->update([
                'total_rows' => $totalRows,
                'message' => "Processing {$totalRows} rows..."
            ]);

            // Perform the import
            Excel::import(new CargoImport($this->batchId), $fullPath);
            $batch->refresh();
            // Refreshes the batch model to get the latest status
            
            if ($batch->status !== 'completed' && $batch->status !== 'failed') {
                Log::warning('Import may not have completed properly', [
                    'batch_id' => $this->batchId,
                    'final_status' => $batch->status,
                    'processed_rows' => $batch->processed_rows,
                    'total_rows' => $batch->total_rows
                ]);
                // Logs a warning if the batch status is not completed or completed_with_errors
            }

            Log::info('Import job completed successfully', [
                'batch_id' => $this->batchId,
                'status' => $batch->status,
                'processed_rows' => $batch->processed_rows,
                'total_rows' => $batch->total_rows
            ]);
            // Logs successful completion with batch details

        } catch (ValidationException $e) {
            $this->handleValidationException($batch, $e);
            // Handles validation exceptions from CargoImport (e.g., invalid data)
            
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            $this->handleException($batch, 'Invalid file format or corrupted file: ' . $e->getMessage(), $e);
            // Handles PhpSpreadsheet-specific exceptions (e.g., corrupted file)
            
        } catch (\Exception $e) {
            $this->handleException($batch, $e->getMessage(), $e);
            // Handles general exceptions during import
            
        } finally {
            $this->cleanup();
            // Cleans up the uploaded file regardless of success or failure
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $exception)
    {
        Log::error('Import job failed completely', [
            'batch_id' => $this->batchId,
            'file_path' => $this->filePath,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
        // Logs the job failure with details and stack trace

        try {
            $batch = ImportBatch::find($this->batchId);
            // Attempts to find the batch record
            if ($batch) {
                $batch->update([
                    'status' => 'failed',
                    'message' => 'Job failed: ' . $exception->getMessage(),
                    'completed_at' => now()
                ]);
                // Updates the batch status to 'failed' with the error message
            }
        } catch (\Exception $e) {
            Log::error('Failed to update batch status on job failure', [
                'batch_id' => $this->batchId,
                'errors' => $e->getMessage()
            ]);
        }

        $this->cleanup();
    }

    /**
     * Validate file format
     */
    private function validateFileFormat(string $filePath): void
    {
        // Method to validate the file format and integrity
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $allowedExtensions = ['xlsx', 'xls', 'csv'];
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception("Unsupported file format: {$extension}. Allowed formats: " . implode(', ', $allowedExtensions));
        }
        // Try to create a reader to validate the file
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            // Creates a PhpSpreadsheet reader with data-only mode and non-empty cells
            
            // Test read the first few rows
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            // Loads the spreadsheet and gets the active worksheet
            
            // Check if there's at least a header row
            if ($worksheet->getHighestRow() < 1) {
                throw new \Exception('File appears to be completely empty.');
            }
            // Throws an exception if the file has no rows (not even a header)
            
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            throw new \Exception('File appears to be corrupted or invalid: ' . $e->getMessage());
            // Throws an exception if the file is corrupted or unreadable
        }
    }

    /**
     * Count total rows efficiently
     */
    private function countTotalRows(string $filePath): int
    {
        try {
            // Use Excel toArray to get row count (excluding header)
            $import = new CargoImport($this->batchId);
            $array = Excel::toArray($import, $filePath);
            // Loads the file into an array using Laravel Excel and CargoImport
            
            if (!isset($array[0]) || !is_array($array[0])) {
                return 0;
            }
            // Returns 0 if the array is empty or invalid
            
            // Count non-empty rows (excluding header row)
            $dataRows = array_filter($array[0], function ($row) {
                return !empty(array_filter($row, function ($cell) {
                    return !is_null($cell) && $cell !== '';
                }));
            });
            // Filters out empty rows, considering a row non-empty if it has at least one non-null, non-empty cell
            
            return count($dataRows);
            // Returns the count of non-empty data rows
            
        } catch (\Exception $e) {
            Log::error('Failed to count rows using Excel::toArray, trying alternative method', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage()
            ]);
            
            // Fallback method using PhpSpreadsheet directly
            try {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);
                // Creates a PhpSpreadsheet reader with data-only mode
                
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                // Loads the spreadsheet and gets the active worksheet
                
                return max(0, $worksheet->getHighestRow() - 1); // Subtract 1 for header
                // Returns the number of rows minus the header, ensuring a non-negative result
                
            } catch (\Exception $e2) {
                Log::error('All row counting methods failed', [
                    'batch_id' => $this->batchId,
                    'primary_error' => $e->getMessage(),
                    'fallback_error' => $e2->getMessage()
                ]);
                // Logs an error if both row-counting methods fail
                
                throw new \Exception('Unable to determine file row count: ' . $e2->getMessage());
                // Throws an exception with the fallback error message
            }
        }
    }

    /**
     * Handle validation exceptions
     */
    private function handleValidationException(ImportBatch $batch, ValidationException $e): void
    {
        // Method to handle validation exceptions from CargoImport
        $errors = $e->errors();
        $errorCount = count($errors);
        // Gets the validation errors and their count
        
        Log::error('Validation failed for import job', [
            'batch_id' => $this->batchId,
            'error_count' => $errorCount,
            'errors' => $errors
        ]);
        // Logs the validation errors with batch details

        $batch->update([
            'status' => 'failed',
            'message' => "Validation failed with {$errorCount} error(s)",
            'error_count' => $errorCount,
            'validation_errors' => json_encode($errors),
            'completed_at' => now()
        ]);
        // Updates the batch with failure details, including error count and serialized errors
        throw $e;
    }

    /**
     * Handle general exceptions
     */
    private function handleException(ImportBatch $batch, string $message, \Exception $e): void
    {
        // Method to handle general exceptions during import
        Log::error('Import job failed with exception', [
            'batch_id' => $this->batchId,
            'file_path' => $this->filePath,
            'message' => $message,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        // Logs the exception with batch, file, and stack trace details

        $batch->update([
            'status' => 'failed',
            'message' => $message,
            'completed_at' => now()
        ]);
        // Updates the batch status to 'failed' with the error message
    }

    /**
     * Clean up temporary files
     */
    private function cleanup(): void
    {
        // Method to clean up the uploaded file
        try {
            if (Storage::exists($this->filePath)) {
                Storage::delete($this->filePath);
                Log::info('Cleanup: Deleted import file', [
                    'batch_id' => $this->batchId,
                    'file_path' => $this->filePath
                ]);
                // Deletes the file and logs the cleanup
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete import file during cleanup', [
                'batch_id' => $this->batchId,
                'file_path' => $this->filePath,
                'error' => $e->getMessage()
            ]);
            // Logs a warning if file deletion fails
        }
    }

    /**
     * Get the tags that should be assigned to the job
     */
    public function tags()
    {
        // Method to define tags for the job for monitoring and filtering
        return ['import', 'cargo', "batch:{$this->batchId}"];
    }
}