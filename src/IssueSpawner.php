<?php

namespace OpenProducer\IssueSpawner;

class IssueSpawner
{
    private GitHubClient $github;
    private ConfigParser $parser;
    private string $logsDir;

    public function __construct(GitHubClient $github, ConfigParser $parser, string $logsDir = 'logs')
    {
        $this->github = $github;
        $this->parser = $parser;
        $this->logsDir = $logsDir;

        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
    }

    /**
     * Process a control issue and spawn multiple issues
     */
    public function process(string $owner, string $repo, int $controlIssueNumber): array
    {
        // Step 1: Get the control issue
        $controlIssue = $this->github->getIssue($owner, $repo, $controlIssueNumber);
        $issueBody = $controlIssue['body'] ?? '';

        echo "📋 Processing control issue #{$controlIssueNumber}...\n";

        // Step 2: Parse configuration
        try {
            $config = $this->parser->parse($issueBody);
            $this->parser->validate($config);
        } catch (\InvalidArgumentException $e) {
            $errorMsg = "❌ Configuration error: " . $e->getMessage();
            echo $errorMsg . "\n";
            $this->github->createComment($owner, $repo, $controlIssueNumber, $errorMsg);
            return ['error' => $e->getMessage()];
        }

        echo "✅ Configuration parsed successfully\n";
        echo "   Count: {$config['count']}\n";
        echo "   Dry run: " . ($config['dry_run'] ? 'yes' : 'no') . "\n";
        echo "   Unique by: {$config['unique_by']}\n";

        // Step 3: Check permissions
        $permissions = $this->github->checkPermissions($owner, $repo);
        if (!$permissions['can_create_issues']) {
            $errorMsg = "❌ Bot does not have permissions to create issues in this repository";
            echo $errorMsg . "\n";
            $this->github->createComment($owner, $repo, $controlIssueNumber, $errorMsg);
            return ['error' => 'Insufficient permissions'];
        }

        if ($permissions['has_push_access']) {
            echo "⚠️  Warning: Token has push access. Bot will only create issues, never modify repository files.\n";
        }

        // Step 4: Check threshold
        $threshold = (int)($_ENV['THRESHOLD_WARNING'] ?? 100);
        if ($config['count'] > $threshold && !$config['dry_run']) {
            $warningMsg = "⚠️ Warning: You are about to create {$config['count']} issues (threshold: {$threshold}).\n"
                . "Please review the configuration and reply with `@bot confirm` to proceed or `@bot cancel` to abort.";
            echo $warningMsg . "\n";
            $this->github->createComment($owner, $repo, $controlIssueNumber, $warningMsg);
            return ['status' => 'awaiting_confirmation', 'count' => $config['count']];
        }

        // Step 5: Get existing issues for deduplication
        echo "🔍 Fetching existing issues for deduplication...\n";
        $existingIssues = $this->github->listIssues($owner, $repo);
        echo "   Found " . count($existingIssues) . " existing issues\n";

        // Step 6: Generate issues to create
        $issuesToCreate = $this->generateIssues($config, $controlIssueNumber, $existingIssues);
        echo "📝 Generated " . count($issuesToCreate) . " issues (after deduplication)\n";

        // Step 7: Dry run or actual creation
        if ($config['dry_run']) {
            return $this->handleDryRun($owner, $repo, $controlIssueNumber, $issuesToCreate);
        } else {
            return $this->createIssues($owner, $repo, $controlIssueNumber, $issuesToCreate, $config);
        }
    }

    /**
     * Generate issue data from template
     */
    private function generateIssues(array $config, int $parentIssue, array $existingIssues): array
    {
        $issues = [];
        $template = $config['template'];
        $componentsList = $config['components_list'];

        if (!empty($componentsList)) {
            // Generate one issue per component
            foreach ($componentsList as $component) {
                $title = $this->replacePlaceholders($template, $component, 'title');
                $body = $this->replacePlaceholders($template, $component, 'body');
                $body = str_replace('{parent_issue}', (string)$parentIssue, $body);

                if (!$this->isDuplicate($title, $body, $existingIssues, $config['unique_by'])) {
                    $issues[] = [
                        'title' => $title,
                        'body' => $body,
                        'labels' => $config['labels'],
                        'assignees' => $config['assignees'],
                    ];
                }
            }
        } else {
            // Generate N identical issues (or with index)
            for ($i = 1; $i <= $config['count']; $i++) {
                $component = ['index' => $i];
                $title = $this->replacePlaceholders($template, $component, 'title');
                $body = $this->replacePlaceholders($template, $component, 'body');
                $body = str_replace('{parent_issue}', (string)$parentIssue, $body);

                if (!$this->isDuplicate($title, $body, $existingIssues, $config['unique_by'])) {
                    $issues[] = [
                        'title' => $title,
                        'body' => $body,
                        'labels' => $config['labels'],
                        'assignees' => $config['assignees'],
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Replace placeholders in template
     */
    private function replacePlaceholders(string $template, array $data, string $section): string
    {
        // Extract title or body from template
        $content = $template;

        if ($section === 'title') {
            // Try to extract title line
            if (preg_match('/Title:\s*(.+?)(?:\n|$)/i', $template, $matches)) {
                $content = $matches[1];
            } else {
                // Use first line as title
                $lines = explode("\n", $template);
                $content = $lines[0];
            }
        } elseif ($section === 'body') {
            // Everything after "Body:" or skip title line
            if (preg_match('/Body:\s*(.+)/is', $template, $matches)) {
                $content = $matches[1];
            } else {
                $lines = explode("\n", $template);
                array_shift($lines); // Remove title line
                $content = implode("\n", $lines);
            }
        }

        // Replace placeholders
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }

        return trim($content);
    }

    /**
     * Check if issue is duplicate
     */
    private function isDuplicate(string $title, string $body, array $existingIssues, string $uniqueBy): bool
    {
        foreach ($existingIssues as $issue) {
            switch ($uniqueBy) {
                case 'title':
                    if ($issue['title'] === $title) {
                        return true;
                    }
                    break;
                case 'body':
                    if (($issue['body'] ?? '') === $body) {
                        return true;
                    }
                    break;
                case 'hash':
                    $existingHash = md5($issue['title'] . $issue['body']);
                    $newHash = md5($title . $body);
                    if ($existingHash === $newHash) {
                        return true;
                    }
                    break;
            }
        }

        return false;
    }

    /**
     * Handle dry-run mode
     */
    private function handleDryRun(string $owner, string $repo, int $controlIssueNumber, array $issues): array
    {
        echo "🔍 DRY RUN MODE - No issues will be created\n";

        $preview = "## 🔍 Dry Run Preview\n\n";
        $preview .= "The following " . count($issues) . " issues would be created:\n\n";

        foreach ($issues as $i => $issue) {
            $preview .= "### " . ($i + 1) . ". {$issue['title']}\n";
            $preview .= "**Labels:** " . implode(', ', $issue['labels']) . "\n";
            if (!empty($issue['assignees'])) {
                $preview .= "**Assignees:** " . implode(', ', $issue['assignees']) . "\n";
            }
            $preview .= "**Body preview:** " . substr($issue['body'], 0, 200) . "...\n\n";
        }

        $preview .= "\n---\nTo proceed with creation, change `dry_run: false` and re-run the command.";

        $this->github->createComment($owner, $repo, $controlIssueNumber, $preview);

        return [
            'status' => 'dry_run',
            'preview_count' => count($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Create issues with rate limiting
     */
    private function createIssues(string $owner, string $repo, int $controlIssueNumber, array $issues, array $config): array
    {
        $runId = date('YmdHis') . '_' . $controlIssueNumber;
        $logFile = $this->logsDir . "/run_{$runId}.json";

        $created = [];
        $errors = [];
        $rateLimitPerMinute = $config['rate_limit_per_minute'];
        $delayBetweenRequests = 60.0 / $rateLimitPerMinute; // in seconds

        echo "🚀 Creating issues (rate limit: {$rateLimitPerMinute}/min)...\n";

        foreach ($issues as $i => $issueData) {
            try {
                $createdIssue = $this->github->createIssue($owner, $repo, $issueData);

                $created[] = [
                    'id' => $createdIssue['id'],
                    'number' => $createdIssue['number'],
                    'url' => $createdIssue['html_url'],
                    'title' => $createdIssue['title'],
                    'created_at' => $createdIssue['created_at'],
                ];

                echo "   ✅ Created issue #{$createdIssue['number']}: {$createdIssue['title']}\n";

                // Rate limiting
                if ($i < count($issues) - 1) {
                    usleep((int)($delayBetweenRequests * 1000000));
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'title' => $issueData['title'],
                    'error' => $e->getMessage(),
                ];
                echo "   ❌ Error creating issue: {$e->getMessage()}\n";
            }
        }

        // Save log for rollback
        $logData = [
            'run_id' => $runId,
            'control_issue' => $controlIssueNumber,
            'owner' => $owner,
            'repo' => $repo,
            'timestamp' => date('c'),
            'created' => $created,
            'errors' => $errors,
        ];

        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));

        // Post summary to control issue
        $summary = "## ✅ Issue Creation Complete\n\n";
        $summary .= "**Run ID:** `{$runId}`\n";
        $summary .= "**Created:** " . count($created) . " issues\n";
        $summary .= "**Errors:** " . count($errors) . "\n\n";

        if (!empty($created)) {
            $summary .= "### Created Issues\n";
            foreach ($created as $issue) {
                $summary .= "- #{$issue['number']}: [{$issue['title']}]({$issue['url']})\n";
            }
        }

        if (!empty($errors)) {
            $summary .= "\n### Errors\n";
            foreach ($errors as $error) {
                $summary .= "- {$error['title']}: {$error['error']}\n";
            }
        }

        $summary .= "\n---\nLog saved to: `{$logFile}`\n";
        $summary .= "To rollback, use: `@bot rollback {$runId}`";

        $this->github->createComment($owner, $repo, $controlIssueNumber, $summary);

        return [
            'status' => 'completed',
            'run_id' => $runId,
            'created' => count($created),
            'errors' => count($errors),
            'log_file' => $logFile,
        ];
    }

    /**
     * Rollback a run (close created issues)
     */
    public function rollback(string $runId): array
    {
        $logFile = $this->logsDir . "/run_{$runId}.json";

        if (!file_exists($logFile)) {
            return ['error' => "Log file not found for run {$runId}"];
        }

        $logData = json_decode(file_get_contents($logFile), true);
        $owner = $logData['owner'];
        $repo = $logData['repo'];
        $created = $logData['created'];

        echo "🔄 Rolling back run {$runId}...\n";

        $closed = [];
        $errors = [];

        foreach ($created as $issue) {
            try {
                $this->github->closeIssue($owner, $repo, $issue['number']);
                $closed[] = $issue['number'];
                echo "   ✅ Closed issue #{$issue['number']}\n";
            } catch (\Exception $e) {
                $errors[] = [
                    'issue' => $issue['number'],
                    'error' => $e->getMessage(),
                ];
                echo "   ❌ Error closing issue #{$issue['number']}: {$e->getMessage()}\n";
            }
        }

        return [
            'status' => 'rollback_completed',
            'closed' => count($closed),
            'errors' => count($errors),
        ];
    }
}
