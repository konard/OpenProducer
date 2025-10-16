<?php

/**
 * Experiment to test confirmation flow logic
 * This script simulates the confirmation workflow to identify the bug
 */

// Simulate the confirmation flow
echo "=== Testing Confirmation Flow ===\n\n";

// Initial configuration from issue body
$issueBody = "@TheOpenProducerBot\ncount: 5\ntemplate: Create a task\ndry_run: true";

// Step 1: Initial job dispatch
echo "Step 1: Initial job dispatched\n";
$config1 = parseConfig($issueBody);
$isConfirmed1 = false;
echo "  dry_run: " . ($config1['dry_run'] ? 'true' : 'false') . "\n";
echo "  isConfirmed: " . ($isConfirmed1 ? 'true' : 'false') . "\n";
echo "  Should request confirmation? " . (shouldRequestConfirmation($config1, $isConfirmed1) ? 'YES' : 'NO') . "\n";
echo "\n";

// Step 2: User confirms
echo "Step 2: User confirms with '@bot confirm'\n";
echo "  Current code re-parses issue body...\n";
$config2 = parseConfig($issueBody); // BUG: Re-parsing gets dry_run=true again
$isConfirmed2 = true;
echo "  dry_run: " . ($config2['dry_run'] ? 'true' : 'false') . "\n";
echo "  isConfirmed: " . ($isConfirmed2 ? 'true' : 'false') . "\n";
echo "  Should request confirmation? " . (shouldRequestConfirmation($config2, $isConfirmed2) ? 'YES' : 'NO') . "\n";
echo "  BUG: Even with isConfirmed=true, dry_run=true causes loop!\n";
echo "\n";

// Step 3: What should happen
echo "Step 3: What SHOULD happen when confirmed\n";
$config3 = parseConfig($issueBody);
$config3['dry_run'] = false; // Override dry_run when confirmed
$isConfirmed3 = true;
echo "  dry_run: " . ($config3['dry_run'] ? 'true' : 'false') . "\n";
echo "  isConfirmed: " . ($isConfirmed3 ? 'true' : 'false') . "\n";
echo "  Should request confirmation? " . (shouldRequestConfirmation($config3, $isConfirmed3) ? 'YES' : 'NO') . "\n";
echo "  CORRECT: Proceeds to create issues\n";
echo "\n";

function parseConfig(string $body): array
{
    $config = [
        'dry_run' => false,
        'count' => null,
        'template' => '',
    ];

    $lines = explode("\n", $body);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), 'dry_run:')) {
            $value = trim(explode(':', $line, 2)[1]);
            $config['dry_run'] = in_array(strtolower($value), ['true', '1', 'yes']);
        }
        if (str_starts_with(trim($line), 'count:')) {
            $value = trim(explode(':', $line, 2)[1]);
            $config['count'] = (int)$value;
        }
        if (str_starts_with(trim($line), 'template:')) {
            $value = trim(explode(':', $line, 2)[1]);
            $config['template'] = $value;
        }
    }

    return $config;
}

function shouldRequestConfirmation(array $config, bool $isConfirmed): bool
{
    // This matches the logic in ProcessSpawnIssueJob.php line 80
    $requiresConfirmation = false; // Simplified for this test
    return $config['dry_run'] || ($requiresConfirmation && !$isConfirmed);
}
