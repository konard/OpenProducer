<?php

namespace App\Console\Commands;

use App\Jobs\RollbackRunJob;
use App\Models\BotRun;
use Exception;
use Illuminate\Console\Command;

class BotRollbackCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:rollback
                            {--run= : The run ID to rollback}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback a bot run by closing all created issues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $runId = $this->option('run');

        if (!$runId) {
            $this->error('--run option is required');
            return Command::FAILURE;
        }

        try {
            $this->info("Looking up run {$runId}...");

            // Find the run
            $botRun = BotRun::where('run_id', $runId)->first();

            if (!$botRun) {
                $this->error("Run not found: {$runId}");
                return Command::FAILURE;
            }

            // Check if can rollback
            if (!$botRun->canRollback()) {
                $this->error("Cannot rollback run {$runId}. Status: {$botRun->status}");
                return Command::FAILURE;
            }

            $issuesCount = $botRun->createdIssues()->where('status', 'created')->count();

            $this->info("Run details:");
            $this->table(
                ['Key', 'Value'],
                [
                    ['Run ID', $botRun->run_id],
                    ['Repository', $botRun->repository],
                    ['Status', $botRun->status],
                    ['Issues to close', $issuesCount],
                ]
            );

            if (!$this->confirm('Do you want to proceed with rollback?')) {
                $this->info('Rollback cancelled');
                return Command::SUCCESS;
            }

            // Dispatch rollback job
            $this->info('Dispatching rollback job...');
            RollbackRunJob::dispatch($runId, $botRun->repository, $botRun->trigger_issue_number);

            $this->info('✓ Rollback job dispatched successfully');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Failed to rollback: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
