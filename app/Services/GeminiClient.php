<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient implements AiClientInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private float $temperature;
    private int $maxTokens;
    private int $timeout;
    private int $cacheTtl;

    public function __construct()
    {
        $this->apiKey = config('bot.gemini.api_key');
        $this->baseUrl = config('bot.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->model = config('bot.gemini.model', 'gemini-2.5-flash');
        $this->temperature = (float) config('bot.gemini.temperature', 0.3);
        $this->maxTokens = (int) config('bot.gemini.max_tokens', 32000);
        $this->timeout = (int) config('bot.gemini.timeout', 60);
        $this->cacheTtl = (int) config('bot.gemini.cache_ttl', 3600);

        if (empty($this->apiKey)) {
            throw new Exception('Gemini API key is not configured');
        }

        Log::info('GeminiClient initialized', [
            'model' => $this->model,
            'base_url' => $this->baseUrl,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ]);
    }

    /**
     * Determine optimal number of issues based on requirements
     */
    public function determineIssueCount(string $requirements, array $options = []): int
    {
        $cacheKey = 'ai_issue_count_' . md5($requirements);

        return cache()->remember($cacheKey, $this->cacheTtl, function () use ($requirements) {
            try {
                $prompt = "Based on the following requirements, determine the optimal number of GitHub issues to create. Return only a number between 1 and 10.\n\nRequirements: {$requirements}\n\nOptimal number of issues:";

                $response = $this->generateContent($prompt);
                $content = $this->extractContent($response);

                // Extract number from response
                $count = preg_match('/(\d+)/', $content, $matches) ? (int)$matches[1] : 3;

                Log::info('Gemini determined issue count', [
                    'requirements_length' => strlen($requirements),
                    'response_content' => $content,
                    'count' => $count,
                ]);

                return min($count, 10);
            } catch (Exception $e) {
                Log::error('Gemini count determination failed: ' . $e->getMessage());
                return 3; // Fallback to 3 issues
            }
        });
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
        $cacheKey = 'ai_issues_' . md5($template . serialize($componentsList) . $count);

        return cache()->remember($cacheKey, $this->cacheTtl, function () use ($template, $componentsList, $count) {
            try {
                $prompt = $this->buildGenerationPrompt($template, $componentsList, $count);

                $response = $this->generateContent($prompt);
                $content = $this->extractContent($response);

                return $this->parseGeneratedIssues($content, $count);
            } catch (Exception $e) {
                Log::error('Gemini generation failed: ' . $e->getMessage());
                return $this->generateFallbackIssues($template, $count);
            }
        });
    }

    /**
     * Generate content using Gemini API
     */
    private function generateContent(string $prompt): array
    {
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => $this->maxTokens,
                'candidateCount' => 1,
            ]
        ];

        Log::info('Calling Gemini API', [
            'model' => $this->model,
            'prompt_length' => strlen($prompt),
            'url' => "{$this->baseUrl}/models/{$this->model}:generateContent",
        ]);

        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout($this->timeout)
        ->post("{$this->baseUrl}/models/{$this->model}:generateContent", $data);

        if (!$response->successful()) {
            throw new Exception('Gemini API request failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Extract text content from Gemini response
     */
    private function extractContent(array $response): string
    {
        if (empty($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Empty response from Gemini API');
        }

        return $response['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Build prompt for issue generation
     */
    private function buildGenerationPrompt(string $template, array $components, int $count): string
    {
        $componentsText = empty($components) ? '' : "\nComponents to address:\n- " . implode("\n- ", $components);

        return <<<PROMPT
You are a project management expert. Based on the following template, generate {$count} GitHub issues that break down the work into manageable tasks.

Template:
{$template}
{$componentsText}

Generate exactly {$count} issues. For each issue, provide:
1. A clear, descriptive title
2. Detailed description

Format your response as JSON:
```json
[
  {
    "title": "Issue title here",
    "body": "Detailed description here"
  },
  {
    "title": "Another issue title",
    "body": "Another detailed description"
  }
]
```

Make sure each issue is specific, actionable, and follows GitHub best practices.
PROMPT;
    }

    /**
     * Parse generated issues from AI response
     */
    private function parseGeneratedIssues(string $content, int $expectedCount): array
    {
        // Try to extract JSON from the response
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonContent = $matches[1];
        } else {
            $jsonContent = $content;
        }

        $issues = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($issues)) {
            throw new Exception('Failed to parse generated issues JSON');
        }

        Log::info('Parsed generated issues', [
            'expected_count' => $expectedCount,
            'actual_count' => count($issues),
        ]);

        return array_slice($issues, 0, $expectedCount);
    }

    /**
     * Generate fallback issues when AI fails
     */
    private function generateFallbackIssues(string $template, int $count): array
    {
        Log::info('Using fallback generation method', ['count' => $count]);

        $issues = [];
        $lines = array_filter(array_map('trim', explode("\n", $template)));

        for ($i = 0; $i < $count; $i++) {
            $title = !empty($lines[$i]) ? substr($lines[$i], 0, 100) : "Task #" . ($i + 1);
            $body = !empty($lines[$i]) ? $lines[$i] : $template;

            $issues[] = [
                'title' => $title,
                'body' => $body,
            ];
        }

        return $issues;
    }

    /**
     * Generate variations of a given text
     */
    public function generateVariations(string $text, int $count, array $options = []): array
    {
        $cacheKey = 'ai_variations_' . md5($text . $count);

        return cache()->remember($cacheKey, $this->cacheTtl, function () use ($text, $count) {
            try {
                $prompt = "Generate {$count} variations of the following text. Each variation should be unique but maintain the same meaning:\n\n{$text}\n\nProvide the variations as a JSON array: [\"variation 1\", \"variation 2\", \"variation 3\"]";

                $response = $this->generateContent($prompt);
                $content = $this->extractContent($response);

                // Try to extract JSON from the response
                if (preg_match('/\[(.*?)\]/s', $content, $matches)) {
                    $jsonContent = '[' . $matches[1] . ']';
                } else {
                    $jsonContent = $content;
                }

                $variations = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($variations)) {
                    throw new Exception('Failed to parse generated variations JSON');
                }

                Log::info('Generated text variations', [
                    'text_length' => strlen($text),
                    'requested_count' => $count,
                    'actual_count' => count($variations),
                ]);

                return array_slice($variations, 0, $count);
            } catch (Exception $e) {
                Log::error('Gemini variations generation failed: ' . $e->getMessage());

                // Fallback: create simple variations by adding prefixes
                $variations = [];
                $prefixes = ['Updated: ', 'Modified: ', 'Revised: ', 'Enhanced: ', 'Improved: '];

                for ($i = 0; $i < $count; $i++) {
                    $prefix = $prefixes[$i % count($prefixes)];
                    $variations[] = $prefix . $text;
                }

                return $variations;
            }
        });
    }

    /**
     * Check if the AI service is available
     */
    public function isAvailable(): bool
    {
        try {
            // Simple availability check by making a minimal API call
            $testPrompt = "Test prompt";
            $response = $this->generateContent($testPrompt);

            return !empty($response);
        } catch (Exception $e) {
            Log::error('Gemini availability check failed: ' . $e->getMessage());
            return false;
        }
    }
}
