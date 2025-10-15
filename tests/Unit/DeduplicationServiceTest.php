<?php

namespace Tests\Unit;

use App\Models\BotCreatedIssue;
use App\Services\DeduplicationService;
use PHPUnit\Framework\TestCase;

class DeduplicationServiceTest extends TestCase
{
    private DeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeduplicationService();
    }

    public function test_generates_consistent_hash_for_title()
    {
        $title1 = "Fix authentication bug";
        $body1 = "This is the body";

        $title2 = "Fix authentication bug";
        $body2 = "Different body content";

        $hash1 = BotCreatedIssue::generateHash($title1, $body1, 'title');
        $hash2 = BotCreatedIssue::generateHash($title2, $body2, 'title');

        $this->assertEquals($hash1, $hash2);
    }

    public function test_generates_different_hash_for_different_titles()
    {
        $title1 = "Fix authentication bug";
        $title2 = "Fix authorization bug";
        $body = "Same body";

        $hash1 = BotCreatedIssue::generateHash($title1, $body, 'title');
        $hash2 = BotCreatedIssue::generateHash($title2, $body, 'title');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_generates_hash_based_on_body()
    {
        $title = "Same title";
        $body1 = "First body";
        $body2 = "Second body";

        $hash1 = BotCreatedIssue::generateHash($title, $body1, 'body');
        $hash2 = BotCreatedIssue::generateHash($title, $body2, 'body');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_filters_duplicates_from_list()
    {
        $issues = [
            ['title' => 'Issue 1', 'body' => 'Body 1'],
            ['title' => 'Issue 2', 'body' => 'Body 2'],
            ['title' => 'Issue 1', 'body' => 'Body 1'], // Duplicate
            ['title' => 'Issue 3', 'body' => 'Body 3'],
        ];

        $filtered = $this->service->filterDuplicates($issues, 'hash');

        $this->assertCount(3, $filtered);
    }

    public function test_gets_deduplication_stats()
    {
        $issues = [
            ['title' => 'Issue 1', 'body' => 'Body 1'],
            ['title' => 'Issue 2', 'body' => 'Body 2'],
            ['title' => 'Issue 3', 'body' => 'Body 3'],
        ];

        $stats = $this->service->getDeduplicationStats($issues, 'hash');

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals('hash', $stats['unique_by']);
    }
}
