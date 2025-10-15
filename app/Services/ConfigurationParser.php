<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConfigurationParser
{
    /**
     * Parse configuration from issue body
     */
    public function parse(string $issueBody): array
    {
        Log::info('ConfigurationParser::parse called', [
            'issue_body_length' => strlen($issueBody),
            'issue_body_preview' => substr($issueBody, 0, 200),
        ]);

        // Check if body contains the trigger command
        if (!$this->hasTriggerCommand($issueBody)) {
            throw new Exception('Issue body does not contain bot mention trigger');
        }

        $config = [
            'count' => null, // Will be determined by AI if not specified
            'template' => '',
            'labels' => [],
            'assignees' => [],
            'rate_limit_per_minute' => config('bot.behavior.rate_limit_per_minute'),
            'dry_run' => false,
            'unique_by' => 'hash',
            'components_list' => [],
        ];

        // Parse YAML-style or key:value format
        $lines = explode("\n", $issueBody);
        $inTemplateBlock = false;
        $templateLines = [];
        $foundExplicitConfig = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip trigger mentions
            if (Str::startsWith($line, '@TheOpenProducerBot') || Str::startsWith($line, '/spawn-issues')) {
                continue;
            }

            // Handle template block (can be multiline)
            if (Str::startsWith($line, 'template:')) {
                $inTemplateBlock = true;
                $foundExplicitConfig = true;
                $templateValue = trim(Str::after($line, 'template:'));
                if (!empty($templateValue)) {
                    $templateLines[] = $templateValue;
                }
                continue;
            }

            // If we're in a template block and encounter another key, exit template mode
            if ($inTemplateBlock && preg_match('/^[a-z_]+:/', $line)) {
                $inTemplateBlock = false;
                $config['template'] = implode("\n", $templateLines);
                $templateLines = [];
            }

            // Continue collecting template lines
            if ($inTemplateBlock) {
                $templateLines[] = $line;
                continue;
            }

            // Parse other configuration keys
            if (Str::contains($line, ':')) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));
                $key = strtolower($key);

                Log::info('Found potential config line', [
                    'line' => $line,
                    'key' => $key,
                    'value' => $value,
                    'before_foundExplicitConfig' => $foundExplicitConfig,
                ]);

                // Only treat as config if it has both key and value, or if key is a known config key
                $knownConfigKeys = ['count', 'labels', 'assignees', 'rate_limit_per_minute', 'dry_run', 'unique_by', 'components_list', 'template'];

                if (empty($value) && !in_array($key, $knownConfigKeys)) {
                    Log::info('Skipping line - empty value and not a known config key', [
                        'line' => $line,
                        'key' => $key,
                    ]);
                    // Don't treat as explicit config - it's probably just a sentence ending with colon
                    $templateLines[] = $line;
                    continue;
                }

                $foundExplicitConfig = true;

                Log::info('Set foundExplicitConfig to true', [
                    'line' => $line,
                    'key' => $key,
                ]);

                switch ($key) {
                    case 'count':
                        $config['count'] = (int)$value;
                        break;

                    case 'labels':
                        $config['labels'] = $this->parseList($value);
                        break;

                    case 'assignees':
                        $config['assignees'] = $this->parseList($value);
                        break;

                    case 'rate_limit_per_minute':
                        $config['rate_limit_per_minute'] = (int)$value;
                        break;

                    case 'dry_run':
                        $config['dry_run'] = $this->parseBoolean($value);
                        break;

                    case 'unique_by':
                        $config['unique_by'] = in_array($value, ['title', 'body', 'hash'])
                            ? $value
                            : 'hash';
                        break;

                    case 'components_list':
                        $config['components_list'] = $this->parseList($value);
                        break;
                }
            }
        }

        // If template block was at the end
        if ($inTemplateBlock) {
            $config['template'] = implode("\n", $templateLines);
        }

        Log::info('About to extract template', [
            'foundExplicitConfig' => $foundExplicitConfig,
            'current_template' => $config['template'],
            'template_length' => strlen($config['template']),
        ]);

        // If no explicit config was found, treat entire issue body as template
        if (!$foundExplicitConfig) {
            Log::info('Extracting template from comment body');
            $config['template'] = $this->extractTemplateFromBody($issueBody);

            // Debug logging
            Log::info('Template extracted from comment', [
                'template' => $config['template'],
                'template_length' => strlen($config['template']),
                'template_empty' => empty($config['template']),
            ]);
        } else {
            Log::info('Using explicit config template, skipping extraction', [
                'template' => $config['template'],
            ]);
        }

        return $this->validateConfiguration($config);
    }

    /**
     * Check if issue body contains trigger command
     */
    public function hasTriggerCommand(string $issueBody): bool
    {
        // Support both new mention-based trigger and legacy command
        $mentionTrigger = config('bot.commands.mention_trigger', '@xierongchuan');
        $legacyTrigger = config('bot.commands.trigger', '/spawn-issues');

        return Str::contains($issueBody, $mentionTrigger) || Str::contains($issueBody, $legacyTrigger);
    }

    /**
     * Extract template from issue body when no explicit config
     */
    private function extractTemplateFromBody(string $issueBody): string
    {
        $lines = explode("\n", $issueBody);
        $templateLines = [];

        Log::info('extractTemplateFromBody processing', [
            'total_lines' => count($lines),
            'lines' => $lines,
        ]);

        foreach ($lines as $index => $line) {
            $originalLine = $line;
            $line = trim($line);

            Log::info('Processing line', [
                'index' => $index,
                'original' => $originalLine,
                'trimmed' => $line,
                'is_mention' => Str::startsWith($line, '@TheOpenProducerBot'),
                'is_command' => Str::startsWith($line, '/spawn-issues'),
            ]);

            // Skip mention triggers
            if (Str::startsWith($line, '@TheOpenProducerBot') || Str::startsWith($line, '/spawn-issues')) {
                Log::info('Skipping trigger line', ['line' => $line]);
                continue;
            }

            Log::info('Adding to template', ['line' => $line]);
            $templateLines[] = $line;
        }

        $template = trim(implode("\n", $templateLines));

        Log::info('Template extraction result', [
            'template_lines_count' => count($templateLines),
            'template' => $template,
            'template_length' => strlen($template),
            'is_empty' => empty($template),
        ]);

        return $template;
    }

    /**
     * Parse comma-separated or newline-separated list
     */
    private function parseList(string $value): array
    {
        // Handle JSON array format
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Handle comma-separated
        if (Str::contains($value, ',')) {
            return array_map('trim', explode(',', $value));
        }

        // Handle space-separated
        if (Str::contains($value, ' ')) {
            return array_map('trim', explode(' ', $value));
        }

        // Single value
        return [trim($value)];
    }

    /**
     * Parse boolean value
     */
    private function parseBoolean(string $value): bool
    {
        $value = strtolower(trim($value));
        return in_array($value, ['true', '1', 'yes', 'on']);
    }

    /**
     * Validate parsed configuration
     */
    private function validateConfiguration(array $config): array
    {
        Log::info('validateConfiguration called', [
            'config' => $config,
            'template_length' => strlen($config['template'] ?? ''),
            'template_empty' => empty($config['template'] ?? ''),
        ]);

        $maxIssues = config('bot.behavior.max_issues_per_run');

        // Count is now optional - if null, will be determined by AI
        if ($config['count'] !== null && $config['count'] < 1) {
            throw new Exception('Count must be at least 1');
        }

        if ($config['count'] !== null && $config['count'] > $maxIssues && !$config['dry_run']) {
            throw new Exception("Count exceeds maximum allowed ({$maxIssues}). Use dry_run mode or reduce count.");
        }

        if (empty($config['template'])) {
            Log::error('Template validation failed', [
                'template' => $config['template'],
                'template_length' => strlen($config['template'] ?? ''),
                'is_empty' => empty($config['template'] ?? ''),
            ]);
            throw new Exception('Template is required - please provide a description or specification');
        }

        if ($config['rate_limit_per_minute'] < 1) {
            throw new Exception('Rate limit must be at least 1');
        }

        return $config;
    }

    /**
     * Extract command from comment
     */
    public function extractCommand(string $commentBody): ?string
    {
        $commentBody = trim($commentBody);

        $commands = [
            'confirm' => config('bot.commands.confirm', '@TheOpenProducerBot confirm'),
            'cancel' => config('bot.commands.cancel', '@TheOpenProducerBot cancel'),
            'rollback' => config('bot.commands.rollback', '@TheOpenProducerBot rollback last'),
            'status' => config('bot.commands.status', '@TheOpenProducerBot status'),
        ];

        foreach ($commands as $name => $pattern) {
            // Support both string patterns and arrays of patterns
            $patterns = is_array($pattern) ? $pattern : [$pattern];

            foreach ($patterns as $p) {
                if (Str::startsWith($commentBody, $p)) {
                    return $name;
                }
            }
        }

        return null;
    }
}
