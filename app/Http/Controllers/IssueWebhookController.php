<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSpawnIssueJob;
use App\Jobs\RollbackRunJob;
use App\Models\BotRun;
use App\Services\ConfigurationParser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IssueWebhookController extends Controller
{
    private ConfigurationParser $parser;

    public function __construct(ConfigurationParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Handle incoming webhook from GitHub
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Verify webhook signature (optional but recommended)
            $this->verifyWebhookSignature($request);

            $event = $request->header('X-GitHub-Event');
            $payload = $request->all();

            Log::info('Webhook received', [
                'event' => $event,
                'action' => $payload['action'] ?? null,
            ]);

            switch ($event) {
                case 'issues':
                    return $this->handleIssueEvent($payload);

                case 'issue_comment':
                    return $this->handleCommentEvent($payload);

                default:
                    return response()->json(['message' => 'Event not handled'], 200);
            }

        } catch (Exception $e) {
            Log::error('Webhook handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle issue events
     */
    private function handleIssueEvent(array $payload): JsonResponse
    {
        $action = $payload['action'] ?? null;

        // Only handle opened and edited events
        if (!in_array($action, ['opened', 'edited'])) {
            return response()->json(['message' => 'Issue action not handled'], 200);
        }

        $issue = $payload['issue'];
        $repository = $payload['repository']['full_name'];
        $issueNumber = $issue['number'];
        $issueBody = $issue['body'] ?? '';

        // Check if issue contains trigger command
        if (!$this->parser->hasTriggerCommand($issueBody)) {
            return response()->json(['message' => 'No trigger command found'], 200);
        }

        try {
            // Parse configuration
            $configuration = $this->parser->parse($issueBody);

            // Dispatch job
            ProcessSpawnIssueJob::dispatch($repository, $issueNumber, $configuration);

            Log::info('Spawn issue job dispatched', [
                'repository' => $repository,
                'issue_number' => $issueNumber,
            ]);

            return response()->json([
                'message' => 'Job dispatched successfully',
                'repository' => $repository,
                'issue' => $issueNumber,
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to parse configuration or dispatch job', [
                'repository' => $repository,
                'issue_number' => $issueNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to process issue',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle comment events
     */
    private function handleCommentEvent(array $payload): JsonResponse
    {
        $action = $payload['action'] ?? null;

        // Only handle created comments
        if ($action !== 'created') {
            return response()->json(['message' => 'Comment action not handled'], 200);
        }

        $comment = $payload['comment'];
        $issue = $payload['issue'];
        $repository = $payload['repository']['full_name'];
        $issueNumber = $issue['number'];
        $commentBody = $comment['body'] ?? '';

        // Extract command from comment
        $command = $this->parser->extractCommand($commentBody);

        // Handle bot commands first
        if ($command) {
            Log::info('Bot command received', [
                'command' => $command,
                'repository' => $repository,
                'issue_number' => $issueNumber,
            ]);

            try {
                switch ($command) {
                    case 'confirm':
                        return $this->handleConfirmCommand($repository, $issueNumber, $issue['body']);

                    case 'cancel':
                        return $this->handleCancelCommand($repository, $issueNumber);

                    case 'rollback':
                        return $this->handleRollbackCommand($repository, $issueNumber);

                    case 'status':
                        return $this->handleStatusCommand($repository, $issueNumber);

                    default:
                        return response()->json(['message' => 'Unknown command'], 200);
                }

            } catch (Exception $e) {
                Log::error('Failed to handle command', [
                    'command' => $command,
                    'repository' => $repository,
                    'issue_number' => $issueNumber,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Failed to execute command',
                    'message' => $e->getMessage(),
                ], 400);
            }
        }

        // Check if comment contains trigger command for spawning issues
        if ($this->parser->hasTriggerCommand($commentBody)) {
            Log::info('Spawn command found in comment', [
                'repository' => $repository,
                'issue_number' => $issueNumber,
                'comment_body' => $commentBody,
            ]);

            try {
                // Parse configuration from comment
                $configuration = $this->parser->parse($commentBody);

                // Dispatch job
                ProcessSpawnIssueJob::dispatch($repository, $issueNumber, $configuration);

                Log::info('Spawn issue job dispatched from comment', [
                    'repository' => $repository,
                    'issue_number' => $issueNumber,
                ]);

                return response()->json([
                    'message' => 'Job dispatched successfully from comment',
                    'repository' => $repository,
                    'issue' => $issueNumber,
                ], 200);

            } catch (Exception $e) {
                Log::error('Failed to parse configuration from comment or dispatch job', [
                    'repository' => $repository,
                    'issue_number' => $issueNumber,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Failed to process comment',
                    'message' => $e->getMessage(),
                ], 400);
            }
        }

        // No bot command found
        Log::info('No bot command found in comment', [
            'repository' => $repository,
            'issue_number' => $issueNumber,
            'comment_body' => $commentBody,
        ]);
        return response()->json(['message' => 'No bot command found'], 200);
    }

    /**
     * Handle confirm command
     */
    private function handleConfirmCommand(string $repository, int $issueNumber, string $issueBody): JsonResponse
    {
        // Find pending run for this issue
        $pendingRun = BotRun::where('repository', $repository)
            ->where('trigger_issue_number', $issueNumber)
            ->where('status', 'pending')
            ->where('dry_run', true)
            ->latest()
            ->first();

        if (!$pendingRun) {
            Log::warning('No pending run found for confirmation', [
                'repository' => $repository,
                'issue_number' => $issueNumber,
            ]);
            throw new Exception('No pending run found to confirm. Please start a new request.');
        }

        // Get configuration from the pending run instead of re-parsing
        $configuration = $pendingRun->configuration;

        // Override dry_run to false when confirmed
        $configuration['dry_run'] = false;

        // Mark the pending run as confirmed and cancelled (it will be replaced by a new run)
        $pendingRun->update([
            'confirmed' => true,
            'status' => 'cancelled',
        ]);

        Log::info('Confirmation received, dispatching job', [
            'repository' => $repository,
            'issue_number' => $issueNumber,
            'run_id' => $pendingRun->run_id,
            'dry_run_override' => false,
        ]);

        // Dispatch with confirmation and dry_run overridden to false
        ProcessSpawnIssueJob::dispatch($repository, $issueNumber, $configuration, true);

        return response()->json(['message' => 'Confirmation received, processing'], 200);
    }

    /**
     * Handle cancel command
     */
    private function handleCancelCommand(string $repository, int $issueNumber): JsonResponse
    {
        // Find and cancel pending runs
        $cancelled = BotRun::where('repository', $repository)
            ->where('trigger_issue_number', $issueNumber)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        Log::info('Runs cancelled', [
            'repository' => $repository,
            'issue_number' => $issueNumber,
            'count' => $cancelled,
        ]);

        return response()->json([
            'message' => 'Runs cancelled',
            'count' => $cancelled,
        ], 200);
    }

    /**
     * Handle rollback command
     */
    private function handleRollbackCommand(string $repository, int $issueNumber): JsonResponse
    {
        // Find last run for this issue (completed, failed, or running)
        Log::info('Looking for run to rollback', [
            'repository' => $repository,
            'issue_number' => $issueNumber,
        ]);

        $lastRun = BotRun::where('repository', $repository)
            ->where('trigger_issue_number', $issueNumber)
            ->whereIn('status', ['completed', 'failed', 'running'])
            ->latest()
            ->first();

        if (!$lastRun) {
            Log::error('No run found to rollback', [
                'repository' => $repository,
                'issue_number' => $issueNumber,
            ]);
            throw new Exception('No run found to rollback');
        }

        Log::info('Found run to rollback', [
            'run_id' => $lastRun->run_id,
            'status' => $lastRun->status,
            'created_at' => $lastRun->created_at,
        ]);

        // Dispatch rollback job
        RollbackRunJob::dispatch($lastRun->run_id, $repository, $issueNumber);

        return response()->json([
            'message' => 'Rollback job dispatched',
            'run_id' => $lastRun->run_id,
        ], 200);
    }

    /**
     * Handle status command
     */
    private function handleStatusCommand(string $repository, int $issueNumber): JsonResponse
    {
        // Get all runs for this issue
        $runs = BotRun::where('repository', $repository)
            ->where('trigger_issue_number', $issueNumber)
            ->latest()
            ->limit(10)
            ->get();

        $statuses = $runs->map(fn($run) => $run->getSummary());

        Log::info('Status requested', [
            'repository' => $repository,
            'issue_number' => $issueNumber,
            'runs_count' => $runs->count(),
        ]);

        return response()->json([
            'message' => 'Status retrieved',
            'runs' => $statuses,
        ], 200);
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature(Request $request): void
    {
        $secret = config('bot.github.webhook_secret');

        if (empty($secret)) {
            // Skip verification if no secret is configured
            return;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            throw new Exception('Missing webhook signature');
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception('Invalid webhook signature');
        }
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }
}
