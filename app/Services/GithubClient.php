<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubClient
{
    private string $token;
    private string $apiVersion;
    private int $retryAttempts;
    private int $retryBackoffBase;

    public function __construct()
    {
        $this->token = config('bot.github.token');
        $this->apiVersion = config('bot.github.api_version');
        $this->retryAttempts = config('bot.behavior.retry_attempts', 3);
        $this->retryBackoffBase = config('bot.behavior.retry_backoff_base', 2);

        if (empty($this->token)) {
            throw new Exception('GitHub token is not configured');
        }
    }

    /**
     * Get HTTP client with authentication
     */
    private function getClient(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => $this->apiVersion,
        ])->timeout(30);
    }

    /**
     * Execute request with retry logic and exponential backoff
     */
    private function withRetry(callable $callback)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts) {
            try {
                $response = $callback();

                if ($response->successful()) {
                    return $response;
                }

                // Check for rate limiting
                if ($response->status() === 403 || $response->status() === 429) {
                    $retryAfter = $response->header('Retry-After') ?? pow($this->retryBackoffBase, $attempt);
                    Log::warning("GitHub API rate limit hit, retrying after {$retryAfter}s");
                    sleep((int)$retryAfter);
                    $attempt++;
                    continue;
                }

                // Other client errors (4xx) should not be retried
                if ($response->status() >= 400 && $response->status() < 500) {
                    throw new Exception("GitHub API error: " . $response->body());
                }

                // Server errors (5xx) should be retried
                if ($response->status() >= 500) {
                    $backoff = pow($this->retryBackoffBase, $attempt);
                    Log::warning("GitHub API server error, retrying after {$backoff}s");
                    sleep($backoff);
                    $attempt++;
                    continue;
                }

                return $response;
            } catch (Exception $e) {
                $lastException = $e;
                $backoff = pow($this->retryBackoffBase, $attempt);
                Log::warning("GitHub API request failed: {$e->getMessage()}, retrying after {$backoff}s");
                sleep($backoff);
                $attempt++;
            }
        }

        throw new Exception("GitHub API request failed after {$this->retryAttempts} attempts: " .
            ($lastException ? $lastException->getMessage() : 'Unknown error'));
    }

    /**
     * Get issue details
     */
    public function getIssue(string $owner, string $repo, int $issueNumber): array
    {
        $response = $this->withRetry(function () use ($owner, $repo, $issueNumber) {
            return $this->getClient()->get("https://api.github.com/repos/{$owner}/{$repo}/issues/{$issueNumber}");
        });

        return $response->json();
    }

    /**
     * Create a new issue
     */
    public function createIssue(string $owner, string $repo, array $data): array
    {
        $response = $this->withRetry(function () use ($owner, $repo, $data) {
            return $this->getClient()->post("https://api.github.com/repos/{$owner}/{$repo}/issues", $data);
        });

        return $response->json();
    }

    /**
     * Create a comment on an issue
     */
    public function createComment(string $owner, string $repo, int $issueNumber, string $body): array
    {
        $response = $this->withRetry(function () use ($owner, $repo, $issueNumber, $body) {
            return $this->getClient()->post(
                "https://api.github.com/repos/{$owner}/{$repo}/issues/{$issueNumber}/comments",
                ['body' => $body]
            );
        });

        return $response->json();
    }

    /**
     * Update an issue
     */
    public function updateIssue(string $owner, string $repo, int $issueNumber, array $data): array
    {
        $response = $this->withRetry(function () use ($owner, $repo, $issueNumber, $data) {
            return $this->getClient()->patch(
                "https://api.github.com/repos/{$owner}/{$repo}/issues/{$issueNumber}",
                $data
            );
        });

        return $response->json();
    }

    /**
     * Delete an issue (by closing it, as GitHub doesn't allow true deletion via API)
     */
    public function closeIssue(string $owner, string $repo, int $issueNumber): array
    {
        return $this->updateIssue($owner, $repo, $issueNumber, [
            'state' => 'closed',
            'state_reason' => 'not_planned'
        ]);
    }

    /**
     * Get issue comments
     */
    public function getIssueComments(string $owner, string $repo, int $issueNumber): array
    {
        $response = $this->withRetry(function () use ($owner, $repo, $issueNumber) {
            return $this->getClient()->get(
                "https://api.github.com/repos/{$owner}/{$repo}/issues/{$issueNumber}/comments"
            );
        });

        return $response->json();
    }

    /**
     * List repository issues
     */
    public function listIssues(string $owner, string $repo, array $params = []): array
    {
        $response = $this->withRetry(function () use ($owner, $repo, $params) {
            return $this->getClient()->get(
                "https://api.github.com/repos/{$owner}/{$repo}/issues",
                $params
            );
        });

        return $response->json();
    }

    /**
     * Check token permissions (scopes)
     */
    public function checkTokenScopes(): array
    {
        $response = $this->getClient()->get('https://api.github.com/user');

        if (!$response->successful()) {
            throw new Exception('Failed to verify GitHub token');
        }

        $scopes = $response->header('X-OAuth-Scopes');

        Log::info('GitHub token scopes', [
            'scopes' => $scopes,
            'warning' => 'Bot will NOT perform any git push operations even if token has write access'
        ]);

        return [
            'scopes' => $scopes ? explode(', ', $scopes) : [],
            'user' => $response->json(),
        ];
    }

    /**
     * Validate repository access
     */
    public function validateRepositoryAccess(string $owner, string $repo): bool
    {
        try {
            $response = $this->getClient()->get("https://api.github.com/repos/{$owner}/{$repo}");
            return $response->successful();
        } catch (Exception $e) {
            Log::error("Repository access validation failed: {$e->getMessage()}");
            return false;
        }
    }
}
