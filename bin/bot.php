#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use OpenProducer\IssueSpawner\GitHubClient;
use OpenProducer\IssueSpawner\ConfigParser;
use OpenProducer\IssueSpawner\IssueSpawner;
use Dotenv\Dotenv;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// Parse command line arguments
$options = getopt('', ['repo:', 'issue:', 'rollback:', 'help']);

if (isset($options['help']) || empty($options)) {
    echo <<<HELP
OpenProducer Issue Spawner Bot
===============================

Usage:
  php bin/bot.php --repo=owner/repo --issue=123
  php bin/bot.php --rollback=run_id

Options:
  --repo=owner/repo    Repository in format owner/repo
  --issue=123          Control issue number
  --rollback=run_id    Rollback a previous run
  --help               Show this help message

Environment Variables:
  GITHUB_TOKEN         GitHub Personal Access Token (required)
  THRESHOLD_WARNING    Maximum issues without confirmation (default: 100)
  DEFAULT_RATE_LIMIT   Default rate limit per minute (default: 30)
  LOGS_DIR             Directory for logs (default: logs)
  DEBUG                Enable debug mode (default: false)

Examples:
  # Process control issue
  php bin/bot.php --repo=myorg/myrepo --issue=42

  # Rollback a run
  php bin/bot.php --rollback=20250315120000_42

HELP;
    exit(0);
}

// Validate token
$token = $_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN');
if (!$token) {
    echo "❌ Error: GITHUB_TOKEN not set in environment\n";
    echo "Please set GITHUB_TOKEN in .env file or environment variable\n";
    exit(1);
}

// Initialize components
$github = new GitHubClient($token);
$parser = new ConfigParser();
$logsDir = $_ENV['LOGS_DIR'] ?? 'logs';
$spawner = new IssueSpawner($github, $parser, $logsDir);

try {
    // Handle rollback
    if (isset($options['rollback'])) {
        $runId = $options['rollback'];
        echo "🔄 Starting rollback for run: {$runId}\n";
        $result = $spawner->rollback($runId);

        if (isset($result['error'])) {
            echo "❌ Error: {$result['error']}\n";
            exit(1);
        }

        echo "✅ Rollback completed\n";
        echo "   Closed: {$result['closed']} issues\n";
        echo "   Errors: {$result['errors']}\n";
        exit(0);
    }

    // Handle spawn issues
    if (!isset($options['repo']) || !isset($options['issue'])) {
        echo "❌ Error: --repo and --issue are required\n";
        echo "Use --help for usage information\n";
        exit(1);
    }

    $repo = $options['repo'];
    $issueNumber = (int)$options['issue'];

    // Parse repo
    if (!preg_match('#^([^/]+)/([^/]+)$#', $repo, $matches)) {
        echo "❌ Error: Invalid repository format. Use: owner/repo\n";
        exit(1);
    }

    $owner = $matches[1];
    $repoName = $matches[2];

    echo "🤖 OpenProducer Issue Spawner Bot\n";
    echo "==================================\n";
    echo "Repository: {$owner}/{$repoName}\n";
    echo "Control Issue: #{$issueNumber}\n";
    echo "\n";

    // Process the control issue
    $result = $spawner->process($owner, $repoName, $issueNumber);

    // Display result
    if (isset($result['error'])) {
        echo "\n❌ Failed: {$result['error']}\n";
        exit(1);
    }

    if ($result['status'] === 'dry_run') {
        echo "\n✅ Dry run completed\n";
        echo "   Preview: {$result['preview_count']} issues would be created\n";
        exit(0);
    }

    if ($result['status'] === 'awaiting_confirmation') {
        echo "\n⏸️  Awaiting user confirmation\n";
        echo "   Issues to create: {$result['count']}\n";
        exit(0);
    }

    if ($result['status'] === 'completed') {
        echo "\n✅ Process completed successfully\n";
        echo "   Run ID: {$result['run_id']}\n";
        echo "   Created: {$result['created']} issues\n";
        echo "   Errors: {$result['errors']}\n";
        echo "   Log: {$result['log_file']}\n";
        exit(0);
    }

} catch (\Exception $e) {
    echo "\n❌ Fatal error: {$e->getMessage()}\n";
    if ($_ENV['DEBUG'] ?? false) {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}
