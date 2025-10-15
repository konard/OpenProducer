<?php

namespace OpenProducer\IssueSpawner\Tests;

use PHPUnit\Framework\TestCase;
use OpenProducer\IssueSpawner\ConfigParser;

class ConfigParserTest extends TestCase
{
    private ConfigParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ConfigParser();
    }

    public function testParseBasicConfig(): void
    {
        $issueBody = <<<BODY
/spawn-issues

count: 20
labels: ["frontend", "agent-task"]
rate_limit_per_minute: 30
dry_run: true
unique_by: title

template:
Title: Test Task {index}
Body: This is a test task
BODY;

        $config = $this->parser->parse($issueBody);

        $this->assertEquals(20, $config['count']);
        $this->assertContains('frontend', $config['labels']);
        $this->assertContains('auto-agent-task', $config['labels']);
        $this->assertEquals(30, $config['rate_limit_per_minute']);
        $this->assertTrue($config['dry_run']);
        $this->assertEquals('title', $config['unique_by']);
        $this->assertNotEmpty($config['template']);
    }

    public function testParseWithComponentsList(): void
    {
        $issueBody = <<<BODY
/spawn-issues

count: 5

components_list:
* { "component_name": "users-list", "path": "resources/views/users" }
* { "component_name": "leads-table", "path": "resources/views/leads" }

template:
Title: Migrate {component_name}
Body: Path: {path}
BODY;

        $config = $this->parser->parse($issueBody);

        $this->assertCount(2, $config['components_list']);
        $this->assertEquals('users-list', $config['components_list'][0]['component_name']);
        $this->assertEquals('resources/views/users', $config['components_list'][0]['path']);
        $this->assertEquals('leads-table', $config['components_list'][1]['component_name']);
    }

    public function testParseDefaultValues(): void
    {
        $issueBody = "/spawn-issues\n\ntemplate:\nTitle: Test\nBody: Test body";

        $config = $this->parser->parse($issueBody);

        $this->assertEquals(10, $config['count']);
        $this->assertEquals(30, $config['rate_limit_per_minute']);
        $this->assertFalse($config['dry_run']);
        $this->assertEquals('title', $config['unique_by']);
        $this->assertContains('auto-agent-task', $config['labels']);
    }

    public function testMissingCommandThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing /spawn-issues command');

        $issueBody = "count: 10\ntemplate: Test";
        $this->parser->parse($issueBody);
    }

    public function testValidateCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('count must be at least 1');

        $config = ['count' => 0, 'unique_by' => 'title', 'rate_limit_per_minute' => 30];
        $this->parser->validate($config);
    }

    public function testValidateCountMax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('count must not exceed 1000');

        $config = ['count' => 1001, 'unique_by' => 'title', 'rate_limit_per_minute' => 30];
        $this->parser->validate($config);
    }

    public function testValidateUniqueBy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unique_by must be one of: title, body, hash');

        $config = ['count' => 10, 'unique_by' => 'invalid', 'rate_limit_per_minute' => 30];
        $this->parser->validate($config);
    }

    public function testValidateRateLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('rate_limit_per_minute must be between 1 and 5000');

        $config = ['count' => 10, 'unique_by' => 'title', 'rate_limit_per_minute' => 0];
        $this->parser->validate($config);
    }

    public function testParseAssignees(): void
    {
        $issueBody = <<<BODY
/spawn-issues

count: 5
assignees: ["user1", "user2"]

template:
Title: Test
Body: Test
BODY;

        $config = $this->parser->parse($issueBody);

        $this->assertCount(2, $config['assignees']);
        $this->assertContains('user1', $config['assignees']);
        $this->assertContains('user2', $config['assignees']);
    }

    public function testParseEmptyAssignees(): void
    {
        $issueBody = <<<BODY
/spawn-issues

count: 5
assignees: []

template:
Title: Test
Body: Test
BODY;

        $config = $this->parser->parse($issueBody);

        $this->assertEmpty($config['assignees']);
    }
}
