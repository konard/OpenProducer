<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSpawnIssueJob;
use App\Services\ConfigurationParser;
use App\Services\GithubClient;
use Exception;
use Illuminate\Console\Command;

class BotProcessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:process
                            {--issue= : The issue number to process}
                            {--repo= : The repository in format owner/repo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a /spawn-issues command from a specific issue';

    /**
     * Execute the console command.
     */
    public function handle(GithubClient $github, ConfigurationParser $parser): int
    {
        $issueNumber = $this->option('issue');
        $repository = $this->option('repo');

        if (!$issueNumber || !$repository) {
            $this->error('Both --issue and --repo options are required');
            return Command::FAILURE;
        }

        [$owner, $repo] = explode('/', $repository);

        try {
            $this->info("Fetching issue #{$issueNumber} from {$repository}...");

            // Fetch issue
            $issue = $github->getIssue($owner, $repo, (int)$issueNumber);
            $issueBody = $issue['body'] ?? '';

            // Check for trigger command
            if (!$parser->hasTriggerCommand($issueBody)) {
                $this->error('Issue does not contain /spawn-issues command');
                return Command::FAILURE;
            }

            // Parse configuration
            $this->info('Parsing configuration...');
            $configuration = $parser->parse($issueBody);

            $this->info('Configuration parsed successfully:');
            $this->table(
                ['Key', 'Value'],
                [
                    ['count', $configuration['count']],
                    ['dry_run', $configuration['dry_run'] ? 'yes' : 'no'],
                    ['unique_by', $configuration['unique_by']],
                    ['labels', implode(', ', $configuration['labels'])],
                ]
            );

            // Dispatch job
            $this->info('Dispatching job...');
            ProcessSpawnIssueJob::dispatch($repository, (int)$issueNumber, $configuration);

            $this->info('✓ Job dispatched successfully');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Failed to process issue: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
