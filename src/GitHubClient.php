<?php

namespace OpenProducer\IssueSpawner;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class GitHubClient
{
    private Client $client;
    private string $token;
    private int $retryAttempts = 3;
    private float $retryDelay = 1.0; // seconds

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->client = new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => 'token ' . $token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'OpenProducer-IssueSpawner/1.0',
            ],
        ]);
    }

    /**
     * Get issue details
     */
    public function getIssue(string $owner, string $repo, int $issueNumber): array
    {
        $response = $this->requestWithRetry('GET', "repos/{$owner}/{$repo}/issues/{$issueNumber}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * List all open issues in repository (for deduplication)
     */
    public function listIssues(string $owner, string $repo, int $perPage = 100): array
    {
        $issues = [];
        $page = 1;

        do {
            $response = $this->requestWithRetry('GET', "repos/{$owner}/{$repo}/issues", [
                'query' => [
                    'state' => 'open',
                    'per_page' => $perPage,
                    'page' => $page,
                ],
            ]);

            $pageIssues = json_decode($response->getBody()->getContents(), true);
            $issues = array_merge($issues, $pageIssues);
            $page++;

            // Check if there are more pages
            $linkHeader = $response->getHeader('Link');
            $hasNextPage = !empty($linkHeader) && strpos($linkHeader[0], 'rel="next"') !== false;
        } while ($hasNextPage && count($issues) < 1000); // Safety limit

        return $issues;
    }

    /**
     * Create a new issue
     */
    public function createIssue(string $owner, string $repo, array $data): array
    {
        $response = $this->requestWithRetry('POST', "repos/{$owner}/{$repo}/issues", [
            'json' => $data,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Create a comment on an issue
     */
    public function createComment(string $owner, string $repo, int $issueNumber, string $body): array
    {
        $response = $this->requestWithRetry('POST', "repos/{$owner}/{$repo}/issues/{$issueNumber}/comments", [
            'json' => ['body' => $body],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Close an issue
     */
    public function closeIssue(string $owner, string $repo, int $issueNumber): array
    {
        $response = $this->requestWithRetry('PATCH', "repos/{$owner}/{$repo}/issues/{$issueNumber}", [
            'json' => ['state' => 'closed'],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get rate limit status
     */
    public function getRateLimit(): array
    {
        $response = $this->requestWithRetry('GET', 'rate_limit');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Make request with retry and exponential backoff
     */
    private function requestWithRetry(string $method, string $uri, array $options = []): ResponseInterface
    {
        $attempt = 0;

        while ($attempt < $this->retryAttempts) {
            try {
                return $this->client->request($method, $uri, $options);
            } catch (RequestException $e) {
                $attempt++;

                // Check if it's a rate limit error (403) or server error (5xx)
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

                if ($statusCode === 403 || $statusCode >= 500) {
                    if ($attempt >= $this->retryAttempts) {
                        throw $e;
                    }

                    // Exponential backoff: 1s, 2s, 4s, etc.
                    $delay = $this->retryDelay * pow(2, $attempt - 1);

                    // Check Retry-After header
                    if ($e->getResponse() && $e->getResponse()->hasHeader('Retry-After')) {
                        $retryAfter = (int)$e->getResponse()->getHeader('Retry-After')[0];
                        $delay = max($delay, $retryAfter);
                    }

                    usleep((int)($delay * 1000000));
                } else {
                    // Non-retryable error
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Max retry attempts reached');
    }

    /**
     * Check if token has necessary permissions
     */
    public function checkPermissions(string $owner, string $repo): array
    {
        try {
            $response = $this->requestWithRetry('GET', "repos/{$owner}/{$repo}");
            $repoData = json_decode($response->getBody()->getContents(), true);

            return [
                'can_read' => true,
                'can_create_issues' => $repoData['permissions']['push'] ?? false,
                'has_push_access' => $repoData['permissions']['push'] ?? false,
            ];
        } catch (\Exception $e) {
            return [
                'can_read' => false,
                'can_create_issues' => false,
                'has_push_access' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
