<?php

namespace App\Jobs;

use App\Models\BotRun;
use App\Services\GithubClient;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RollbackRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    private string $runId;
    private string $repository;
    private int $commentIssueNumber;

    /**
     * Create a new job instance.
     */
    public function __construct(string $runId, string $repository, int $commentIssueNumber)
    {
        $this->runId = $runId;
        $this->repository = $repository;
        $this->commentIssueNumber = $commentIssueNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(GithubClient $github): void
    {
        [$owner, $repo] = explode('/', $this->repository);

        try {
            Log::info('Starting rollback', [
                'run_id' => $this->runId,
                'repository' => $this->repository,
            ]);

            // Find the bot run
            $botRun = BotRun::where('run_id', $this->runId)->first();

            if (!$botRun) {
                throw new Exception("Bot run not found: {$this->runId}");
            }

            // Check if rollback is possible
            if (!$botRun->canRollback()) {
                throw new Exception("Cannot rollback run {$this->runId}. Run status: {$botRun->status}");
            }

            // Get issues to delete
            $issuesToDelete = $botRun->createdIssues()
                ->where('status', 'created')
                ->get();

            if ($issuesToDelete->isEmpty()) {
                throw new Exception("No issues to rollback for run {$this->runId}");
            }

            $deletedCount = 0;
            $failedCount = 0;

            // Close each issue
            foreach ($issuesToDelete as $issue) {
                try {
                    $github->closeIssue($owner, $repo, $issue->issue_number);
                    $issue->markAsDeleted();
                    $deletedCount++;

                    Log::info('Issue closed during rollback', [
                        'run_id' => $this->runId,
                        'issue_number' => $issue->issue_number,
                    ]);

                    // Small delay to respect rate limits
                    usleep(500000); // 0.5 seconds

                } catch (Exception $e) {
                    Log::error('Failed to close issue during rollback', [
                        'run_id' => $this->runId,
                        'issue_number' => $issue->issue_number,
                        'error' => $e->getMessage(),
                    ]);
                    $issue->markAsFailed();
                    $failedCount++;
                }
            }

            // Post rollback summary
            $comment = <<<COMMENT
🔄 **Rollback completed**

**Run ID**: `{$this->runId}`
**Issues closed**: {$deletedCount}
**Failed to close**: {$failedCount}

All issues created in this run have been closed.
COMMENT;

            $github->createComment($owner, $repo, $this->commentIssueNumber, $comment);

            Log::info('Rollback completed', [
                'run_id' => $this->runId,
                'deleted' => $deletedCount,
                'failed' => $failedCount,
            ]);

        } catch (Exception $e) {
            Log::error('Rollback failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Post error comment
            try {
                $github->createComment(
                    $owner,
                    $repo,
                    $this->commentIssueNumber,
                    "❌ **Rollback failed**\n\n**Run ID**: `{$this->runId}`\n\n**Error**: {$e->getMessage()}"
                );
            } catch (Exception $commentError) {
                Log::error('Failed to post rollback error comment: ' . $commentError->getMessage());
            }

            throw $e;
        }
    }
}
