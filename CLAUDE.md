# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **OpenProducer** - a Laravel-based GitHub bot that acts as an orchestrator/meta-bot for breaking down large specifications into manageable issues using AI. The bot operates exclusively through GitHub Issues API and never modifies repository files.

## Key Architecture Components

### Core Services
- **OpenAiCompatibleClient** ([app/Services/OpenAiCompatibleClient.php](app/Services/OpenAiCompatibleClient.php)): Main AI service implementing `AiClientInterface`, handles intelligent issue count determination and content generation
- **GithubClient** ([app/Services/GithubClient.php](app/Services/GithubClient.php)): GitHub API wrapper for issue operations
- **ConfigurationParser** ([app/Services/ConfigurationParser.php](app/Services/ConfigurationParser.php)): Parses issue commands and configuration
- **ContentFilter** ([app/Services/ContentFilter.php](app/Services/ContentFilter.php)): Security filtering for prohibited content
- **DeduplicationService** ([app/Services/DeduplicationService.php](app/Services/DeduplicationService.php)): Prevents duplicate issue creation

### Job Processing
- **ProcessSpawnIssueJob** ([app/Jobs/ProcessSpawnIssueJob.php](app/Jobs/ProcessSpawnIssueJob.php)): Main job that orchestrates the entire issue creation workflow
- **RollbackRunJob** ([app/Jobs/RollbackRunJob.php](app/Jobs/RollbackRunJob.php)): Handles rollback of created issues

### Models
- **BotRun** ([app/Models/BotRun.php](app/Models/BotRun.php)): Tracks execution runs with status and metadata
- **BotCreatedIssue** ([app/Models/BotCreatedIssue.php](app/Models/BotCreatedIssue.php)): Records created issues with deduplication hashes

### Commands
- **BotProcessCommand** ([app/Console/Commands/BotProcessCommand.php](app/Console/Commands/BotProcessCommand.php)): CLI manual processing
- **BotStatusCommand** ([app/Console/Commands/BotStatusCommand.php](app/Console/Commands/BotStatusCommand.php)): Status monitoring
- **BotRollbackCommand** ([app/Console/Commands/BotRollbackCommand.php](app/Console/Commands/BotRollbackCommand.php)): Rollback operations

## Common Development Commands

### Setup & Installation
```bash
# Fresh installation
composer run setup

# Manual setup steps
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### Development Workflow
```bash
# Start development environment (includes server, queue, logs, vite)
composer run dev

# Individual services
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work --tries=3
php artisan pail --timeout=0
npm run dev
```

### Testing
```bash
# Run all tests
composer run test
# or
php artisan test

# Run specific test suites
./vendor/bin/phpunit tests/Unit/ConfigurationParserTest.php
./vendor/bin/phpunit tests/Unit/DeduplicationServiceTest.php
./vendor/bin/phpunit tests/Feature/ExampleTest.php
```

### Code Quality
```bash
# Laravel Pint (code formatting)
./vendor/bin/pint

# PHP CS Fixer
./vendor/bin/php-cs-fixer fix

# PHP CodeSniffer
./vendor/bin/phpcs --standard=PSR12 app/
```

### Bot Operations
```bash
# Manual issue processing
php artisan bot:process --issue=123 --repo=owner/repo

# Check bot status
php artisan bot:status
php artisan bot:status --run=run_20240101_120000_abc123
php artisan bot:status --repo=owner/repo --limit=20

# Rollback a run
php artisan bot:rollback --run=run_20240101_120000_abc123
```

## Configuration

### Environment Variables (.env)
- `GITHUB_APP_MODE`: token or app
- `GITHUB_TOKEN`: Personal access token (for token mode)
- `OPENAI_API_BASE_URL`: OpenAI-compatible API endpoint
- `OPENAI_API_KEY`: API key for AI service
- `OPENAI_PROVIDER`: Provider (ZAI, OPENAI, CUSTOM)
- `OPENAI_MODEL`: Model name (e.g., zai-glm-4.6)
- `BOT_RUN_RATE_LIMIT_PER_MINUTE`: Rate limiting (default: 30)
- `BOT_MAX_ISSUES_PER_RUN`: Maximum issues per run (default: 100)

### Bot Configuration (config/bot.php)
The bot behavior is configured through `config/bot.php` with sections for:
- OpenAI API settings (base URL, key, model, temperature, etc.)
- Behavior settings (rate limits, maximum issues, content filtering)
- Security settings (prohibited keywords, confirmation thresholds)

## Security Architecture

- **No file modifications**: Bot only uses GitHub Issues API, never modifies repository files
- **Content filtering**: Automatically detects and flags prohibited content
- **Rate limiting**: Respects GitHub API limits with configurable delays
- **Webhook verification**: Optional signature verification for webhooks
- **Confirmation workflow**: High-risk operations require manual confirmation

## Database Schema

- **bot_runs**: Track execution runs with run_id, status, configuration, and timing
- **bot_created_issues**: Record created issues with deduplication hashes and metadata
- **jobs**: Laravel's standard job queue table
- **cache**: Laravel's cache table for AI response caching

## Testing Strategy

- Unit tests for core services ([tests/Unit/](tests/Unit/))
- Feature tests for webhook processing ([tests/Feature/](tests/Feature/))
- Integration tests for AI client fallback behavior
- Test database uses SQLite in memory

## Development Notes

- The bot supports both `/spawn-issues` command format and `@xierongchuan` mention format
- AI can automatically determine optimal issue count if not specified
- All AI responses are cached to reduce API calls
- Fallback mode works without AI by using simple template duplication
- Uses Laravel queues for async processing with retry logic