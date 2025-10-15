<?php

namespace OpenProducer\IssueSpawner;

class ConfigParser
{
    /**
     * Parse the issue body to extract configuration
     */
    public function parse(string $issueBody): array
    {
        $lines = explode("\n", $issueBody);

        // Check for /spawn-issues command
        $hasCommand = false;
        foreach ($lines as $line) {
            if (trim($line) === '/spawn-issues') {
                $hasCommand = true;
                break;
            }
        }

        if (!$hasCommand) {
            throw new \InvalidArgumentException('Missing /spawn-issues command in issue body');
        }

        $config = [
            'count' => 10,
            'template' => '',
            'labels' => ['auto-agent-task'],
            'assignees' => [],
            'rate_limit_per_minute' => 30,
            'dry_run' => false,
            'unique_by' => 'title',
            'components_list' => [],
        ];

        $content = implode("\n", $lines);

        // Parse count
        if (preg_match('/count:\s*(\d+)/i', $content, $matches)) {
            $config['count'] = (int)$matches[1];
        }

        // Parse rate_limit_per_minute
        if (preg_match('/rate_limit_per_minute:\s*(\d+)/i', $content, $matches)) {
            $config['rate_limit_per_minute'] = (int)$matches[1];
        }

        // Parse dry_run
        if (preg_match('/dry_run:\s*(true|false)/i', $content, $matches)) {
            $config['dry_run'] = strtolower($matches[1]) === 'true';
        }

        // Parse unique_by
        if (preg_match('/unique_by:\s*(title|body|hash)/i', $content, $matches)) {
            $config['unique_by'] = strtolower($matches[1]);
        }

        // Parse labels (YAML-like array)
        if (preg_match('/labels:\s*\[(.*?)\]/s', $content, $matches)) {
            $labelsStr = $matches[1];
            $labels = array_map(function($label) {
                return trim(trim($label), '"\'');
            }, explode(',', $labelsStr));
            $config['labels'] = array_merge($config['labels'], array_filter($labels));
        }

        // Parse assignees
        if (preg_match('/assignees:\s*\[(.*?)\]/s', $content, $matches)) {
            $assigneesStr = $matches[1];
            if (trim($assigneesStr)) {
                $assignees = array_map(function($assignee) {
                    return trim(trim($assignee), '"\'');
                }, explode(',', $assigneesStr));
                $config['assignees'] = array_filter($assignees);
            }
        }

        // Parse template (everything between "template:" and next field or end)
        if (preg_match('/template:\s*(.*?)(?=\n(?:count|labels|assignees|rate_limit|dry_run|unique_by|components_list):|$)/is', $content, $matches)) {
            $config['template'] = trim($matches[1]);
        }

        // Parse components_list (simple bullet list format)
        if (preg_match('/components_list:\s*((?:\*.*?\n?)+)/is', $content, $matches)) {
            $componentLines = explode("\n", $matches[1]);
            foreach ($componentLines as $line) {
                if (preg_match('/\*\s*\{\s*"component_name":\s*"([^"]+)".*?"path":\s*"([^"]+)"/i', $line, $compMatches)) {
                    $config['components_list'][] = [
                        'component_name' => $compMatches[1],
                        'path' => $compMatches[2],
                    ];
                }
            }
        }

        return $config;
    }

    /**
     * Validate configuration
     */
    public function validate(array $config): void
    {
        if ($config['count'] < 1) {
            throw new \InvalidArgumentException('count must be at least 1');
        }

        if ($config['count'] > 1000) {
            throw new \InvalidArgumentException('count must not exceed 1000 (safety limit)');
        }

        if (!in_array($config['unique_by'], ['title', 'body', 'hash'])) {
            throw new \InvalidArgumentException('unique_by must be one of: title, body, hash');
        }

        if ($config['rate_limit_per_minute'] < 1 || $config['rate_limit_per_minute'] > 5000) {
            throw new \InvalidArgumentException('rate_limit_per_minute must be between 1 and 5000');
        }
    }
}
