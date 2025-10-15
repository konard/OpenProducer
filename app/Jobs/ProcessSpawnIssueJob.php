<?php

namespace App\Jobs;

use App\Models\BotRun;
use App\Models\BotCreatedIssue;
use App\Services\AiClientInterface;
use App\Services\ConfigurationParser;
use App\Services\ContentFilter;
use App\Services\DeduplicationService;
use App\Services\GithubClient;
use App\Services\OpenAiCompatibleClient;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSpawnIssueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    private string $repository;
    private int $triggerIssueNumber;
    private array $configuration;
    private bool $isConfirmed;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $repository,
        int $triggerIssueNumber,
        array $configuration,
        bool $isConfirmed = false
    ) {
        $this->repository = $repository;
        $this->triggerIssueNumber = $triggerIssueNumber;
        $this->configuration = $configuration;
        $this->isConfirmed = $isConfirmed;
    }

    /**
     * Execute the job.
     */
    public function handle(
        GithubClient $github,
        AiClientInterface $aiClient,
        ContentFilter $contentFilter,
        DeduplicationService $deduplication
    ): void {
        [$owner, $repo] = explode('/', $this->repository);

        // Create or get bot run record
        $botRun = $this->createBotRun();

        try {
            Log::info('Processing spawn issue job', [
                'run_id' => $botRun->run_id,
                'repository' => $this->repository,
                'issue' => $this->triggerIssueNumber,
            ]);

            // Validate repository access
            if (!$github->validateRepositoryAccess($owner, $repo)) {
                throw new Exception('Cannot access repository. Check token permissions.');
            }

            // Check content filtering
            $warnings = $contentFilter->validateConfiguration($this->configuration);
            $requiresConfirmation = $contentFilter->requiresConfirmation($this->configuration);

            // If dry run or requires confirmation and not confirmed yet
            if ($this->configuration['dry_run'] || ($requiresConfirmation && !$this->isConfirmed)) {
                $this->handleDryRunOrConfirmation($github, $owner, $repo, $botRun, $warnings);
                return;
            }

            // Mark as started
            $botRun->markAsStarted();

            // Generate issues using AI
            $issues = $this->generateIssues($aiClient);

            // Filter duplicates
            $issues = $deduplication->filterDuplicates($issues, $this->configuration['unique_by']);

            // Update planned count
            $botRun->update(['issues_planned' => count($issues)]);

            // Create issues
            $createdIssues = $this->createIssues($github, $owner, $repo, $issues, $botRun);

            // Post summary
            $this->postSummary($github, $owner, $repo, $botRun, $createdIssues);

            // Mark as completed
            $botRun->markAsCompleted();

            Log::info('Spawn issue job completed', [
                'run_id' => $botRun->run_id,
                'issues_created' => count($createdIssues),
            ]);

        } catch (Exception $e) {
            Log::error('Spawn issue job failed', [
                'run_id' => $botRun->run_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $botRun->markAsFailed($e->getMessage());

            // Post error comment
            try {
                $github->createComment(
                    $owner,
                    $repo,
                    $this->triggerIssueNumber,
                    "❌ **Bot run failed**\n\n**Run ID**: `{$botRun->run_id}`\n\n**Error**: {$e->getMessage()}"
                );
            } catch (Exception $commentError) {
                Log::error('Failed to post error comment: ' . $commentError->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Create bot run record
     */
    private function createBotRun(): BotRun
    {
        return BotRun::create([
            'run_id' => BotRun::generateRunId(),
            'repository' => $this->repository,
            'trigger_issue_number' => $this->triggerIssueNumber,
            'status' => 'pending',
            'configuration' => $this->configuration,
            'dry_run' => $this->configuration['dry_run'],
            'confirmed' => $this->isConfirmed,
            'issues_planned' => $this->configuration['count'],
        ]);
    }

    /**
     * Handle dry run or confirmation request
     */
    private function handleDryRunOrConfirmation(
        GithubClient $github,
        string $owner,
        string $repo,
        BotRun $botRun,
        array $warnings
    ): void {
        $warningsText = !empty($warnings)
            ? "\n\n⚠️ **Warnings**:\n" . implode("\n", array_map(fn($w) => "- {$w}", $warnings))
            : '';

        $labelsText = !empty($this->configuration['labels'])
            ? implode(', ', $this->configuration['labels'])
            : '';

        $comment = <<<COMMENT
🤖 **Dry Run / Confirmation Required**

**Run ID**: `{$botRun->run_id}`

**Configuration**:
- Count: {$this->configuration['count']}
- Template:
```
{$this->configuration['template']}
```
- Labels: `{$labelsText}`
- Unique by: `{$this->configuration['unique_by']}`
{$warningsText}

**Preview**: This will create {$this->configuration['count']} issues based on the template above.

To proceed, reply with: `@bot confirm`
To cancel, reply with: `@bot cancel`
COMMENT;

        $github->createComment($owner, $repo, $this->triggerIssueNumber, $comment);

        Log::info('Dry run preview posted', ['run_id' => $botRun->run_id]);
    }

    /**
     * Generate issues using AI
     */
    private function generateIssues(AiClientInterface $aiClient): array
    {
        Log::info('Generating issues with AI', [
            'count' => $this->configuration['count'],
            'components' => count($this->configuration['components_list']),
        ]);

        return $aiClient->generateIssueBodies(
            $this->configuration['template'],
            $this->configuration['components_list'],
            $this->configuration['count']
        );
    }

    /**
     * Create issues on GitHub
     */
    private function createIssues(
        GithubClient $github,
        string $owner,
        string $repo,
        array $issues,
        BotRun $botRun
    ): array {
        $createdIssues = [];
        $rateLimitDelay = 60 / $this->configuration['rate_limit_per_minute'];

        foreach ($issues as $index => $issueData) {
            try {
                $title = $issueData['title'] ?? "Auto-generated task #" . ($index + 1);
                $body = $issueData['body'] ?? $this->configuration['template'];
                $hash = $issueData['hash'] ?? BotCreatedIssue::generateHash($title, $body);

                // Add parent issue reference
                $body .= "\n\n---\n*Auto-generated by bot. Parent issue: #{$this->triggerIssueNumber} | Run ID: `{$botRun->run_id}`*";

                // Create issue
                $response = $github->createIssue($owner, $repo, [
                    'title' => $title,
                    'body' => $body,
                    'labels' => array_merge($this->configuration['labels'], ['auto-agent-task']),
                    'assignees' => $this->configuration['assignees'],
                ]);

                // Store in database
                $createdIssue = BotCreatedIssue::create([
                    'run_id' => $botRun->run_id,
                    'repository' => $this->repository,
                    'issue_number' => $response['number'],
                    'issue_url' => $response['html_url'],
                    'issue_title' => $title,
                    'issue_body' => $body,
                    'hash' => $hash,
                    'labels' => $this->configuration['labels'],
                ]);

                $createdIssues[] = $createdIssue;
                $botRun->incrementIssuesCreated();

                Log::info('Issue created', [
                    'run_id' => $botRun->run_id,
                    'issue_number' => $response['number'],
                    'title' => $title,
                ]);

                // Rate limiting
                if ($index < count($issues) - 1) {
                    sleep((int)$rateLimitDelay);
                }

            } catch (Exception $e) {
                Log::error('Failed to create issue', [
                    'run_id' => $botRun->run_id,
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $createdIssues;
    }

    /**
     * Post summary comment
     */
    private function postSummary(
        GithubClient $github,
        string $owner,
        string $repo,
        BotRun $botRun,
        array $createdIssues
    ): void {
        $issueLinks = array_map(
            fn($issue) => "- #{$issue->issue_number}: [{$issue->issue_title}]({$issue->issue_url})",
            $createdIssues
        );

        $issueLinksText = implode("\n", $issueLinks);
        $summary = $botRun->getSummary();

        $comment = <<<COMMENT
✅ **Bot run completed**

**Run ID**: `{$botRun->run_id}`
**Status**: {$summary['status']}
**Issues created**: {$summary['issues_created']} / {$summary['issues_planned']}
**Duration**: {$summary['duration']}

**Created issues**:
{$issueLinksText}

To rollback this run, reply with: `@bot rollback last`
COMMENT;

        $github->createComment($owner, $repo, $this->triggerIssueNumber, $comment);
    }
}
