<?php

namespace Tests\Feature;

use App\Jobs\ProcessSpawnIssueJob;
use App\Models\BotRun;
use App\Services\ConfigurationParser;
use App\Services\GithubClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConfirmationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock GitHub client to avoid actual API calls
        $this->mock(GithubClient::class, function ($mock) {
            $mock->shouldReceive('createComment')->andReturn(true);
            $mock->shouldReceive('validateRepositoryAccess')->andReturn(true);
        });
    }

    /** @test */
    public function it_requests_confirmation_when_dry_run_is_true()
    {
        Queue::fake();

        $issueBody = "@TheOpenProducerBot\ncount: 3\ntemplate: Test task\ndry_run: true";

        // Simulate webhook receiving issue with dry_run
        $response = $this->postJson('/api/webhook', [
            'action' => 'opened',
            'issue' => [
                'number' => 1,
                'body' => $issueBody,
            ],
            'repository' => [
                'full_name' => 'owner/repo',
            ],
        ], [
            'X-GitHub-Event' => 'issues',
        ]);

        $response->assertStatus(200);
        Queue::assertPushed(ProcessSpawnIssueJob::class);
    }

    /** @test */
    public function it_does_not_loop_when_confirming_dry_run()
    {
        // Create a pending run with dry_run=true
        $configuration = [
            'count' => 3,
            'template' => 'Test task',
            'labels' => [],
            'assignees' => [],
            'rate_limit_per_minute' => 30,
            'dry_run' => true,
            'unique_by' => 'hash',
            'components_list' => [],
        ];

        $botRun = BotRun::create([
            'run_id' => BotRun::generateRunId(),
            'repository' => 'owner/repo',
            'trigger_issue_number' => 1,
            'status' => 'pending',
            'configuration' => $configuration,
            'dry_run' => true,
            'confirmed' => false,
            'issues_planned' => 3,
        ]);

        Queue::fake();

        // Simulate confirmation comment
        $response = $this->postJson('/api/webhook', [
            'action' => 'created',
            'comment' => [
                'body' => '@TheOpenProducerBot confirm',
            ],
            'issue' => [
                'number' => 1,
                'body' => "@TheOpenProducerBot\ncount: 3\ntemplate: Test task\ndry_run: true",
            ],
            'repository' => [
                'full_name' => 'owner/repo',
            ],
        ], [
            'X-GitHub-Event' => 'issue_comment',
        ]);

        $response->assertStatus(200);

        // Verify job was dispatched
        Queue::assertPushed(ProcessSpawnIssueJob::class, function ($job) {
            // The job should have dry_run=false even though original config had true
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('configuration');
            $property->setAccessible(true);
            $config = $property->getValue($job);

            // Verify dry_run is overridden to false
            $this->assertFalse($config['dry_run'], 'dry_run should be false after confirmation');

            // Verify isConfirmed is true
            $confirmedProperty = $reflection->getProperty('isConfirmed');
            $confirmedProperty->setAccessible(true);
            $isConfirmed = $confirmedProperty->getValue($job);
            $this->assertTrue($isConfirmed, 'isConfirmed should be true');

            return true;
        });

        // Verify the original run was marked as cancelled and confirmed
        $botRun->refresh();
        $this->assertEquals('cancelled', $botRun->status);
        $this->assertTrue($botRun->confirmed);
    }

    /** @test */
    public function it_handles_confirmation_without_pending_run()
    {
        // No pending run exists
        Queue::fake();

        // Simulate confirmation comment without a pending run
        $response = $this->postJson('/api/webhook', [
            'action' => 'created',
            'comment' => [
                'body' => '@TheOpenProducerBot confirm',
            ],
            'issue' => [
                'number' => 1,
                'body' => "@TheOpenProducerBot\ncount: 3\ntemplate: Test task",
            ],
            'repository' => [
                'full_name' => 'owner/repo',
            ],
        ], [
            'X-GitHub-Event' => 'issue_comment',
        ]);

        // Should return error since no pending run exists
        $response->assertStatus(400);
        $response->assertJsonFragment(['error' => 'Failed to execute command']);

        // No job should be dispatched
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_uses_stored_configuration_not_reparsed_issue_body()
    {
        // Create a pending run with specific configuration
        $originalConfiguration = [
            'count' => 5,
            'template' => 'Original template from comment',
            'labels' => ['bug', 'auto-generated'],
            'assignees' => [],
            'rate_limit_per_minute' => 30,
            'dry_run' => true,
            'unique_by' => 'hash',
            'components_list' => ['ComponentA', 'ComponentB'],
        ];

        $botRun = BotRun::create([
            'run_id' => BotRun::generateRunId(),
            'repository' => 'owner/repo',
            'trigger_issue_number' => 1,
            'status' => 'pending',
            'configuration' => $originalConfiguration,
            'dry_run' => true,
            'confirmed' => false,
            'issues_planned' => 5,
        ]);

        Queue::fake();

        // Issue body has DIFFERENT configuration (to simulate comment vs issue body difference)
        $issueBody = "@TheOpenProducerBot\ncount: 2\ntemplate: Different template";

        // Simulate confirmation comment
        $response = $this->postJson('/api/webhook', [
            'action' => 'created',
            'comment' => [
                'body' => '@TheOpenProducerBot confirm',
            ],
            'issue' => [
                'number' => 1,
                'body' => $issueBody, // Different from stored configuration
            ],
            'repository' => [
                'full_name' => 'owner/repo',
            ],
        ], [
            'X-GitHub-Event' => 'issue_comment',
        ]);

        $response->assertStatus(200);

        // Verify job uses STORED configuration, not reparsed issue body
        Queue::assertPushed(ProcessSpawnIssueJob::class, function ($job) use ($originalConfiguration) {
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('configuration');
            $property->setAccessible(true);
            $config = $property->getValue($job);

            // Should use original template, not the different one from issue body
            $this->assertEquals('Original template from comment', $config['template']);
            $this->assertEquals(5, $config['count']);
            $this->assertEquals(['bug', 'auto-generated'], $config['labels']);
            $this->assertEquals(['ComponentA', 'ComponentB'], $config['components_list']);
            $this->assertFalse($config['dry_run']); // Should be overridden to false

            return true;
        });
    }

    /** @test */
    public function it_cancels_run_when_cancel_command_received()
    {
        // Create a pending run
        $configuration = [
            'count' => 3,
            'template' => 'Test task',
            'labels' => [],
            'assignees' => [],
            'rate_limit_per_minute' => 30,
            'dry_run' => true,
            'unique_by' => 'hash',
            'components_list' => [],
        ];

        $botRun = BotRun::create([
            'run_id' => BotRun::generateRunId(),
            'repository' => 'owner/repo',
            'trigger_issue_number' => 1,
            'status' => 'pending',
            'configuration' => $configuration,
            'dry_run' => true,
            'confirmed' => false,
            'issues_planned' => 3,
        ]);

        Queue::fake();

        // Simulate cancel comment
        $response = $this->postJson('/api/webhook', [
            'action' => 'created',
            'comment' => [
                'body' => '@TheOpenProducerBot cancel',
            ],
            'issue' => [
                'number' => 1,
                'body' => "@TheOpenProducerBot\ncount: 3\ntemplate: Test task\ndry_run: true",
            ],
            'repository' => [
                'full_name' => 'owner/repo',
            ],
        ], [
            'X-GitHub-Event' => 'issue_comment',
        ]);

        $response->assertStatus(200);

        // Verify the run was cancelled
        $botRun->refresh();
        $this->assertEquals('cancelled', $botRun->status);

        // No new job should be dispatched
        Queue::assertNothingPushed();
    }
}
