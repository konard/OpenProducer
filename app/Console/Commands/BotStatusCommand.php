<?php

namespace App\Console\Commands;

use App\Models\BotRun;
use Illuminate\Console\Command;

class BotStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:status
                            {--run= : Specific run ID to show status for}
                            {--repo= : Filter by repository}
                            {--limit=10 : Number of runs to show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show status of bot runs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $runId = $this->option('run');
        $repository = $this->option('repo');
        $limit = (int)$this->option('limit');

        if ($runId) {
            return $this->showRunDetails($runId);
        }

        return $this->showRunsList($repository, $limit);
    }

    /**
     * Show details for a specific run
     */
    private function showRunDetails(string $runId): int
    {
        $botRun = BotRun::where('run_id', $runId)->first();

        if (!$botRun) {
            $this->error("Run not found: {$runId}");
            return Command::FAILURE;
        }

        $summary = $botRun->getSummary();

        $this->info("Run Details: {$runId}");
        $this->newLine();

        $this->table(
            ['Key', 'Value'],
            [
                ['Run ID', $summary['run_id']],
                ['Repository', $summary['repository']],
                ['Status', $summary['status']],
                ['Dry Run', $summary['dry_run'] ? 'Yes' : 'No'],
                ['Issues Planned', $summary['issues_planned']],
                ['Issues Created', $summary['issues_created']],
                ['Started At', $summary['started_at'] ?? 'N/A'],
                ['Completed At', $summary['completed_at'] ?? 'N/A'],
                ['Duration', $summary['duration'] ?? 'N/A'],
            ]
        );

        // Show created issues
        $createdIssues = $botRun->createdIssues()->get();

        if ($createdIssues->isNotEmpty()) {
            $this->newLine();
            $this->info("Created Issues ({$createdIssues->count()}):");
            $this->newLine();

            $issuesData = $createdIssues->map(function ($issue) {
                return [
                    $issue->issue_number,
                    $issue->issue_title,
                    $issue->status,
                    $issue->issue_url,
                ];
            })->toArray();

            $this->table(
                ['Number', 'Title', 'Status', 'URL'],
                $issuesData
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Show list of runs
     */
    private function showRunsList(?string $repository, int $limit): int
    {
        $query = BotRun::query();

        if ($repository) {
            $query->where('repository', $repository);
        }

        $runs = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($runs->isEmpty()) {
            $this->info('No runs found');
            return Command::SUCCESS;
        }

        $this->info("Recent Bot Runs (showing {$runs->count()}):");
        $this->newLine();

        $runsData = $runs->map(function ($run) {
            return [
                $run->run_id,
                $run->repository,
                $run->status,
                $run->dry_run ? 'Yes' : 'No',
                "{$run->issues_created}/{$run->issues_planned}",
                $run->created_at->diffForHumans(),
            ];
        })->toArray();

        $this->table(
            ['Run ID', 'Repository', 'Status', 'Dry Run', 'Issues', 'Created'],
            $runsData
        );

        $this->newLine();
        $this->info('Use --run=<run_id> to see detailed information about a specific run');

        return Command::SUCCESS;
    }
}
