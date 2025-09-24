<?php

namespace App\Imports;

use App\Models\Cargo;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;

class CargoImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading, WithBatchInserts, WithEvents, SkipsOnError, SkipsOnFailure
{
    public $batchId;
    private $processedCount = 0;
    private $errorCount = 0;
    private $failures = [];
    private $hasErrors = false; // Track if we have any errors
    
    public function __construct(int $batchId)
    {
        $this->batchId = $batchId;
        HeadingRowFormatter::default('slug');
    }

    public function model(array $row)
    { 
        if (empty(array_filter($row))) {
            return null;
        }

        return new Cargo([
            'cargo_no'      => trim($row['cargo_no'] ?? ''),
            'cargo_type'    => trim($row['cargo_type'] ?? ''),
            'cargo_size'    => (int) ($row['cargo_size'] ?? 0),
            'weight'        => $this->parseNumeric($row['weight_kg'] ?? null),
            'remarks'       => trim($row['remarks'] ?? ''),
            'wharfage'      => $this->parseNumeric($row['wharfage_usd'] ?? 0),
            'penalty_days'  => (int) ($row['penalty_days'] ?? 0),
            'storage'       => ((int) ($row['penalty_days'] ?? 0)) * 20,
            'electricity'   => $this->parseNumeric($row['electricity_usd'] ?? 0),
            'destuffing'    => $this->parseNumeric($row['destuffingusd'] ?? 0),
            'lifting'       => $this->parseNumeric($row['lifting_usd'] ?? 0),
            'import_batch_id' => $this->batchId, 
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function rules(): array
    {
        return [
            '*.cargo_no'        => 'required|string|max:255|unique:cargos,cargo_no',
            '*.cargo_type'      => 'required|string|max:100',
            '*.cargo_size'      => 'required|integer|min:1',
            '*.weight_kg'       => 'nullable|numeric|min:0',
            '*.remarks'         => 'nullable|string|max:1000',
            '*.wharfage_usd'    => 'nullable|numeric|min:0',
            '*.penalty_days'    => 'nullable|integer|min:0',
            '*.electricity_usd' => 'nullable|numeric|min:0',
            '*.destuffingusd'   => 'nullable|numeric|min:0',
            '*.lifting_usd'     => 'nullable|numeric|min:0',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            '*.cargo_no.required' => 'Cargo number is required',
            '*.cargo_no.unique' => 'Cargo number already exists',
            '*.cargo_type.required' => 'Cargo type is required',
            '*.cargo_size.required' => 'Cargo size is required',
            '*.cargo_size.min' => 'Cargo size must be at least 1',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function onError(Throwable $e)
    {
        $this->errorCount++;
        $this->hasErrors = true; // Mark that we have errors
        
        Log::error('Cargo import error: ' . $e->getMessage(), [
            'batch_id' => $this->batchId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Immediately update batch status if too many errors
        if ($this->errorCount > 100) {
            $this->updateBatchStatus('failed', 'Too many errors encountered');
            // Stop further processing by throwing an exception
            throw new \Exception('Import failed due to too many errors');
        }
    }

    public function onFailure(Failure ...$failures)
    {
        $this->hasErrors = true; // Mark that we have validation failures
        
        foreach ($failures as $failure) {
            $this->failures[] = [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ];
        }

        Log::warning('Cargo import validation failures', [
            'batch_id' => $this->batchId,
            'error_count' => count($failures),
            'total_failures' => count($this->failures)
        ]);
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                DB::beginTransaction();
                try {
                    $batch = ImportBatch::findOrFail($this->batchId);
                    $batch->update([
                        'status' => 'processing',
                        'started_at' => now()
                    ]);
                    DB::commit();
                } catch (Throwable $e) {
                    DB::rollBack();
                    Log::error('Failed to update batch status to processing', [
                        'batch_id' => $this->batchId,
                        'error' => $e->getMessage()
                    ]);
                    throw $e; // Re-throw to fail the import
                }
            },

            AfterChunk::class => function (AfterChunk $event) {
                try {
                    $chunkSize = $this->chunkSize();
                    $this->processedCount += $chunkSize;
                    
                    $batch = ImportBatch::findOrFail($this->batchId);
                    $batch->increment('processed_rows', $chunkSize);
                    
                    // Calculate progress
                    $progress = 0;
                    if ($batch->total_rows > 0) {
                        $progress = min(100, ($batch->processed_rows / $batch->total_rows) * 100);
                    }
                    
                    // Update status based on errors 
                    $status = 'processing';
                    $message = null;
                    
                    if ($this->hasErrors || $this->errorCount > 0 || !empty($this->failures)) {
                        $status = 'failed';
                        $message = sprintf(
                            'Import failing with %d errors and %d validation failure',
                            $this->errorCount,
                            count($this->failures)
                        );
                    }
                    
                    $batch->update([
                        'progress' => round($progress, 2),
                        'status' => $status,
                        'message' => $message
                    ]);
                    
                    Log::info('Chunk processed', [
                        'batch_id' => $this->batchId,
                        'chunk_size' => $chunkSize,
                        'total_processed' => $this->processedCount,
                        'status' => $status,
                        'has_errors' => $this->hasErrors
                    ]);
                    
                } catch (Throwable $e) {
                    Log::error('Failed to update chunk progress', [
                        'batch_id' => $this->batchId,
                        'error' => $e->getMessage()
                    ]);
                    
                }
            },

            AfterImport::class => function (AfterImport $event) {
                try {
                    $batch = ImportBatch::findOrFail($this->batchId);
                    
                    // Determine final status 
                    $status = 'completed';
                    $message = 'Import completed successfully';
                    
                    // Check for errors or failures
                    if ($this->hasErrors || $this->errorCount > 0 || !empty($this->failures)) {
                        $status = 'failed';
                        $message = sprintf(
                            'Import failing with %d errors and %d validation failure',
                            $this->errorCount,
                            count($this->failures)
                        );
                    }
                    
                    $batch->update([
                        'status' => $status,
                        'processed_rows' => $batch->total_rows,
                        'error_count' => $this->errorCount,
                        'validation_errors' => json_encode($this->failures),
                        'completed_at' => now(),
                        'progress' => 100,
                        'message' => $message
                    ]);
                    
                    Log::info('Import completed', [
                        'batch_id' => $this->batchId,
                        'final_status' => $status,
                        'processed_rows' => $batch->processed_rows,
                        'errors' => $this->errorCount,
                        'validation_failures' => count($this->failures),
                        'has_errors' => $this->hasErrors
                    ]);
                    
                } catch (Throwable $e) {
                    Log::error('Failed to complete import', [
                        'batch_id' => $this->batchId,
                        'error' => $e->getMessage()
                    ]);
                    
                    $this->updateBatchStatus('failed', 'Failed to complete import: ' . $e->getMessage());
                }
            },
        ];
    }

    private function parseNumeric($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return is_numeric($cleaned) ? (float) $cleaned : 0.00;
    }

    private function updateBatchStatus(string $status, string $message = null): void
    {
        try {
            $batch = ImportBatch::findOrFail($this->batchId);
            $batch->update([
                'status' => $status,
                'message' => $message,
                'completed_at' => now()
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to update batch status', [
                'batch_id' => $this->batchId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
}