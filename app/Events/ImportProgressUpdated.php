<?php

namespace App\Events;
use App\Models\ImportBatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    // Uses traits for dispatching events, handling WebSocket interactions, and serializing models

    public $batch;
    public $percentage;
    // Declares public properties for the ImportBatch instance and progress percentage

    /**
     * Create a new event instance.
     *
     * @param ImportBatch $batch
     * @param float $percentage
     */
    public function __construct(ImportBatch $batch, float $percentage = 0)
    {
        $this->batch = $batch;
        $this->percentage = $percentage;
        // Assigns the progress percentage, defaulting to 0
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('import-progress.' . $this->batch->id);
        // Uses a private channel named 'import-progress.{batch_id}' for secure, user-specific updates
    }

    /**
     * Optional: Customize broadcast payload
     */
    public function broadcastWith()
    {
        // Customizes the data sent in the broadcast
        return [
            'batch_id' => $this->batch->id,
            'status' => $this->batch->status,
            'processed_rows' => $this->batch->processed_rows,
            'total_rows' => $this->batch->total_rows,
            'percentage' => $this->percentage,
        ];
        // Returns a payload with batch ID, status, row counts, and percentage for frontend consumption
    }
}