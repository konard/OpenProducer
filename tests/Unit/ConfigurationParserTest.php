<?php

namespace Tests\Unit;

use App\Services\ConfigurationParser;
use Exception;
use PHPUnit\Framework\TestCase;

class ConfigurationParserTest extends TestCase
{
    private ConfigurationParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ConfigurationParser();
    }

    public function test_detects_trigger_command()
    {
        $body = "/spawn-issues\ncount: 5";
        $this->assertTrue($this->parser->hasTriggerCommand($body));
    }

    public function test_rejects_body_without_trigger()
    {
        $body = "This is just a normal issue";
        $this->assertFalse($this->parser->hasTriggerCommand($body));
    }

    public function test_parses_simple_configuration()
    {
        $body = <<<BODY
/spawn-issues
count: 10
template: Fix bug in component
labels: bug, high-priority
dry_run: true
unique_by: title
BODY;

        $config = $this->parser->parse($body);

        $this->assertEquals(10, $config['count']);
        $this->assertEquals('Fix bug in component', $config['template']);
        $this->assertEquals(['bug', 'high-priority'], $config['labels']);
        $this->assertTrue($config['dry_run']);
        $this->assertEquals('title', $config['unique_by']);
    }

    public function test_parses_multiline_template()
    {
        $body = <<<BODY
/spawn-issues
count: 5
template: This is a multi-line template
that spans several lines
and should be preserved
labels: enhancement
BODY;

        $config = $this->parser->parse($body);

        $expectedTemplate = "This is a multi-line template\nthat spans several lines\nand should be preserved";
        $this->assertEquals($expectedTemplate, $config['template']);
        $this->assertEquals(5, $config['count']);
    }

    public function test_parses_components_list()
    {
        $body = <<<BODY
/spawn-issues
count: 3
template: Test component
components_list: auth, api, database
BODY;

        $config = $this->parser->parse($body);

        $this->assertEquals(['auth', 'api', 'database'], $config['components_list']);
    }

    public function test_throws_exception_for_missing_template()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Template is required');

        $body = <<<BODY
/spawn-issues
count: 5
labels: bug
BODY;

        $this->parser->parse($body);
    }

    public function test_throws_exception_for_invalid_count()
    {
        $this->expectException(Exception::class);

        $body = <<<BODY
/spawn-issues
count: 0
template: Some template
BODY;

        $this->parser->parse($body);
    }

    public function test_extracts_confirm_command()
    {
        $comment = "@bot confirm";
        $command = $this->parser->extractCommand($comment);
        $this->assertEquals('confirm', $command);
    }

    public function test_extracts_rollback_command()
    {
        $comment = "@bot rollback last";
        $command = $this->parser->extractCommand($comment);
        $this->assertEquals('rollback', $command);
    }

    public function test_returns_null_for_non_command_comment()
    {
        $comment = "This is just a regular comment";
        $command = $this->parser->extractCommand($comment);
        $this->assertNull($command);
    }
}
