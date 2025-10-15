<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotCreatedIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'repository',
        'issue_number',
        'issue_url',
        'issue_title',
        'issue_body',
        'hash',
        'labels',
        'status',
    ];

    protected $casts = [
        'labels' => 'array',
    ];

    /**
     * Get the bot run that created this issue
     */
    public function botRun(): BelongsTo
    {
        return $this->belongsTo(BotRun::class, 'run_id', 'run_id');
    }

    /**
     * Generate hash for deduplication
     */
    public static function generateHash(string $title, string $body, string $uniqueBy = 'title'): string
    {
        switch ($uniqueBy) {
            case 'title':
                return md5($title);
            case 'body':
                return md5($body);
            case 'hash':
            default:
                return md5($title . '|' . $body);
        }
    }

    /**
     * Check if issue with this hash already exists
     */
    public static function existsByHash(string $hash): bool
    {
        return self::where('hash', $hash)->exists();
    }

    /**
     * Mark issue as deleted
     */
    public function markAsDeleted(): void
    {
        $this->update(['status' => 'deleted']);
    }

    /**
     * Mark issue as failed
     */
    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
