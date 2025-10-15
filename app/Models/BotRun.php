<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BotRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'repository',
        'trigger_issue_number',
        'status',
        'configuration',
        'dry_run',
        'confirmed',
        'issues_created',
        'issues_planned',
        'error_message',
        'log_data',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'configuration' => 'array',
        'log_data' => 'array',
        'dry_run' => 'boolean',
        'confirmed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Generate a unique run ID
     */
    public static function generateRunId(): string
    {
        return 'run_' . date('Ymd_His') . '_' . Str::random(8);
    }

    /**
     * Get the issues created in this run
     */
    public function createdIssues(): HasMany
    {
        return $this->hasMany(BotCreatedIssue::class, 'run_id', 'run_id');
    }

    /**
     * Mark run as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark run as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark run as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark run as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Increment issues created counter
     */
    public function incrementIssuesCreated(): void
    {
        $this->increment('issues_created');
    }

    /**
     * Check if run can be rolled back
     */
    public function canRollback(): bool
    {
        return in_array($this->status, ['completed', 'failed'])
            && $this->createdIssues()->where('status', 'created')->exists();
    }

    /**
     * Get run summary
     */
    public function getSummary(): array
    {
        return [
            'run_id' => $this->run_id,
            'repository' => $this->repository,
            'status' => $this->status,
            'dry_run' => $this->dry_run,
            'issues_planned' => $this->issues_planned,
            'issues_created' => $this->issues_created,
            'started_at' => $this->started_at?->toDateTimeString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'duration' => $this->started_at && $this->completed_at
                ? $this->completed_at->diffInSeconds($this->started_at) . 's'
                : null,
        ];
    }
}
