<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Str;

class ConfigurationParser
{
    /**
     * Parse configuration from issue body
     */
    public function parse(string $issueBody): array
    {
        // Check if body contains the trigger command
        if (!$this->hasTriggerCommand($issueBody)) {
            throw new Exception('Issue body does not contain /spawn-issues command');
        }

        $config = [
            'count' => 1,
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

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip trigger command
            if (Str::startsWith($line, '/spawn-issues')) {
                continue;
            }

            // Handle template block (can be multiline)
            if (Str::startsWith($line, 'template:')) {
                $inTemplateBlock = true;
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

        return $this->validateConfiguration($config);
    }

    /**
     * Check if issue body contains trigger command
     */
    public function hasTriggerCommand(string $issueBody): bool
    {
        $trigger = config('bot.commands.trigger', '/spawn-issues');
        return Str::contains($issueBody, $trigger);
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
        $maxIssues = config('bot.behavior.max_issues_per_run');

        if ($config['count'] < 1) {
            throw new Exception('Count must be at least 1');
        }

        if ($config['count'] > $maxIssues && !$config['dry_run']) {
            throw new Exception("Count exceeds maximum allowed ({$maxIssues}). Use dry_run mode or reduce count.");
        }

        if (empty($config['template'])) {
            throw new Exception('Template is required');
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
            'confirm' => config('bot.commands.confirm', '@bot confirm'),
            'cancel' => config('bot.commands.cancel', '@bot cancel'),
            'rollback' => config('bot.commands.rollback', '@bot rollback last'),
            'status' => config('bot.commands.status', '@bot status'),
        ];

        foreach ($commands as $name => $pattern) {
            if (Str::startsWith($commentBody, $pattern)) {
                return $name;
            }
        }

        return null;
    }
}
