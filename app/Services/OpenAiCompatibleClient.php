<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiCompatibleClient implements AiClientInterface
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private float $temperature;
    private int $maxTokens;
    private int $timeout;
    private int $cacheTtl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('bot.openai.base_url'), '/');
        $this->apiKey = config('bot.openai.api_key');
        $this->model = config('bot.openai.model');
        $this->temperature = config('bot.openai.temperature');
        $this->maxTokens = config('bot.openai.max_tokens');
        $this->timeout = config('bot.openai.timeout');
        $this->cacheTtl = config('bot.openai.cache_ttl');
    }

    /**
     * Determine optimal number of issues based on requirements
     */
    public function determineIssueCount(string $requirements, array $options = []): int
    {
        if (!$this->isAvailable()) {
            Log::warning('AI service is not available, using default count');
            return $this->estimateCountFromRequirements($requirements);
        }

        try {
            $prompt = $this->buildCountDeterminationPrompt($requirements);
            $cacheKey = 'ai_count_' . md5($prompt);

            $result = Cache::remember($cacheKey, $this->cacheTtl, function () use ($prompt, $options) {
                return $this->callAiApi($prompt, $options);
            });

            // Parse the result to extract count
            $count = $this->parseCountFromResponse($result);

            // Validate and cap the count
            $maxIssues = config('bot.behavior.max_issues_per_run', 100);
            $count = max(1, min($count, $maxIssues));

            Log::info('AI determined issue count', ['count' => $count]);

            return $count;
        } catch (Exception $e) {
            Log::error('AI count determination failed: ' . $e->getMessage());
            return $this->estimateCountFromRequirements($requirements);
        }
    }

    /**
     * Generate issue bodies based on template and components
     */
    public function generateIssueBodies(
        string $template,
        array $componentsList,
        int $count,
        array $options = []
    ): array {
        if (!$this->isAvailable()) {
            Log::warning('AI service is not available, falling back to simple template duplication');
            return $this->fallbackGeneration($template, $componentsList, $count);
        }

        try {
            $prompt = $this->buildPrompt($template, $componentsList, $count, $options);
            $cacheKey = 'ai_generation_' . md5($prompt . json_encode($options));

            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($prompt, $count, $options) {
                return $this->callAiApi($prompt, $options);
            });
        } catch (Exception $e) {
            Log::error('AI generation failed: ' . $e->getMessage());
            return $this->fallbackGeneration($template, $componentsList, $count);
        }
    }

    /**
     * Generate variations of a given text
     */
    public function generateVariations(string $text, int $count, array $options = []): array
    {
        if (!$this->isAvailable()) {
            Log::warning('AI service is not available, returning original text');
            return array_fill(0, $count, $text);
        }

        try {
            $prompt = "Generate {$count} variations of the following text while maintaining its core meaning:\n\n{$text}\n\nProvide each variation on a new line, numbered from 1 to {$count}.";

            $cacheKey = 'ai_variations_' . md5($prompt);

            $response = Cache::remember($cacheKey, $this->cacheTtl, function () use ($prompt, $options) {
                return $this->callAiApi($prompt, $options);
            });

            return $this->parseVariations($response);
        } catch (Exception $e) {
            Log::error('AI variation generation failed: ' . $e->getMessage());
            return array_fill(0, $count, $text);
        }
    }

    /**
     * Check if the AI service is available
     */
    public function isAvailable(): bool
    {
        if (empty($this->apiKey) || empty($this->baseUrl)) {
            return false;
        }

        // Quick availability check (cached for 5 minutes)
        return Cache::remember('ai_service_available', 300, function () {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->get($this->baseUrl . '/models');

                return $response->successful();
            } catch (Exception $e) {
                Log::warning('AI service availability check failed: ' . $e->getMessage());
                return false;
            }
        });
    }

    /**
     * Build prompt for issue generation
     */
    private function buildPrompt(string $template, array $componentsList, int $count, array $options): string
    {
        $componentsText = empty($componentsList) ? '' : "\n\nComponents to include:\n" . implode("\n", $componentsList);

        return <<<PROMPT
You are a GitHub issue generation assistant. Generate {$count} unique issue descriptions based on the following template.

Template:
{$template}
{$componentsText}

Requirements:
- Each issue should be unique and follow the template structure
- Maintain professional tone
- Keep titles concise (under 80 characters)
- Include all relevant details in the body
- If components are provided, incorporate them naturally

Generate {$count} issues in the following JSON format:
[
  {
    "title": "Issue title here",
    "body": "Issue body here"
  }
]

Only return valid JSON, no additional text.
PROMPT;
    }

    /**
     * Call the AI API
     */
    private function callAiApi(string $prompt, array $options = []): array
    {
        $model = $options['model'] ?? $this->model;
        $temperature = $options['temperature'] ?? $this->temperature;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;

        Log::info('Calling AI API', [
            'provider' => config('bot.openai.provider'),
            'model' => $model,
            'prompt_length' => strlen($prompt),
        ]);

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->baseUrl . '/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

        if (!$response->successful()) {
            throw new Exception('AI API request failed: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        Log::info('AI API response received', [
            'content_length' => strlen($content),
            'usage' => $data['usage'] ?? null,
        ]);

        // Try to parse as JSON
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // If not JSON, return as raw text
        return ['raw_content' => $content];
    }

    /**
     * Parse variations from AI response
     */
    private function parseVariations(array $response): array
    {
        if (isset($response['raw_content'])) {
            $lines = explode("\n", $response['raw_content']);
            $variations = [];

            foreach ($lines as $line) {
                $line = trim($line);
                // Remove numbering (e.g., "1. ", "1) ", etc.)
                $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
                if (!empty($line)) {
                    $variations[] = $line;
                }
            }

            return $variations;
        }

        return $response;
    }

    /**
     * Fallback generation when AI is not available
     */
    private function fallbackGeneration(string $template, array $componentsList, int $count): array
    {
        Log::info('Using fallback generation method');

        $issues = [];
        for ($i = 1; $i <= $count; $i++) {
            $title = "Auto-generated task #{$i}";
            $body = $template;

            // If components list is provided, add one component per issue
            if (!empty($componentsList)) {
                $componentIndex = ($i - 1) % count($componentsList);
                $component = $componentsList[$componentIndex];
                $body .= "\n\n**Component**: {$component}";
            }

            $issues[] = [
                'title' => $title,
                'body' => $body,
            ];
        }

        return $issues;
    }

    /**
     * Build prompt for determining issue count
     */
    private function buildCountDeterminationPrompt(string $requirements): string
    {
        return <<<PROMPT
You are a task breakdown assistant. Analyze the following requirements/specification and determine the optimal number of smaller issues/tasks needed to implement it.

Requirements:
{$requirements}

Consider:
- Complexity of the requirements
- Natural logical breakdown into subtasks
- Manageable task sizes (each issue should be a focused, actionable unit)
- Typical development workflow (frontend, backend, tests, docs, etc.)
- Don't create too many small tasks or too few large tasks

Respond with ONLY a JSON object in this format:
{
  "count": <number>,
  "reasoning": "<brief explanation>"
}

Example response:
{
  "count": 5,
  "reasoning": "The requirements describe a user authentication system which naturally breaks into: 1) database schema, 2) backend API, 3) frontend UI, 4) tests, 5) documentation"
}
PROMPT;
    }

    /**
     * Parse count from AI response
     */
    private function parseCountFromResponse(array $response): int
    {
        // Handle JSON response
        if (isset($response['count']) && is_numeric($response['count'])) {
            return (int)$response['count'];
        }

        // Handle raw content
        if (isset($response['raw_content'])) {
            $content = $response['raw_content'];

            // Try to extract JSON
            if (preg_match('/\{[^}]*"count"\s*:\s*(\d+)[^}]*\}/', $content, $matches)) {
                return (int)$matches[1];
            }

            // Try to find any number in the response
            if (preg_match('/\b(\d+)\b/', $content, $matches)) {
                return (int)$matches[1];
            }
        }

        // Default fallback
        return 5;
    }

    /**
     * Estimate count from requirements (simple heuristic fallback)
     */
    private function estimateCountFromRequirements(string $requirements): int
    {
        $length = strlen($requirements);
        $lineCount = substr_count($requirements, "\n") + 1;

        // Simple heuristic based on content size
        if ($length < 200 || $lineCount < 3) {
            return 1; // Very simple requirement
        } elseif ($length < 500 || $lineCount < 10) {
            return 3; // Small to medium
        } elseif ($length < 1000 || $lineCount < 20) {
            return 5; // Medium
        } elseif ($length < 2000 || $lineCount < 40) {
            return 8; // Medium to large
        } else {
            return 10; // Large specification
        }
    }
}
