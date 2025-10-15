# Command Reference

## Bot Commands

### Issue Creation Commands

#### @TheOpenProducerBot (Primary Method)
Mention the bot in any issue or comment to trigger intelligent task breakdown.

```
@TheOpenProducerBot

Your requirements here...
```

#### /spawn-issues (Legacy Command)
Still supported for backwards compatibility.

```
/spawn-issues
count: 5
template: Your template here
```

### Control Commands

#### Confirm Command
Proceed with a dry run preview.

```
@TheOpenProducerBot confirm
```

#### Cancel Command
Cancel a pending run.

```
@TheOpenProducerBot cancel
```

#### Rollback Command
Rollback the last completed run by closing all created issues.

```
@TheOpenProducerBot rollback last
```

#### Status Command
Show status of recent runs for the current issue.

```
@TheOpenProducerBot status
```

## Configuration Options

### Basic Configuration

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `template` | string (multiline) | Requirements or template for issues | Required if not in issue body |
| `count` | integer | Number of issues to create | AI-determined based on complexity |
| `labels` | comma-separated | Labels to apply to created issues | [] |
| `assignees` | comma-separated | GitHub users to assign issues to | [] |

### Advanced Configuration

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `dry_run` | boolean | Preview without creating actual issues | false |
| `unique_by` | string | Deduplication strategy: `title`, `body`, or `hash` | hash |
| `components_list` | comma-separated | Logical components to focus on | [] |
| `rate_limit_per_minute` | integer | API rate limit for this run | 30 |

### Configuration Formats

#### YAML-style Format
```
@TheOpenProducerBot

template: Implement user authentication system
Requirements:
- JWT token authentication
- Login and signup flows
- Password reset functionality

count: 4
labels: feature, authentication
components_list: backend, frontend, database
```

#### Key-Value Format
```
@TheOpenProducerBot

template: Build REST API for e-commerce
count: 8
labels: api, backend, feature
assignees: john-doe, jane-smith
dry_run: true
unique_by: title
```

#### Simple Template (Entire Body)
```
@TheOpenProducerBot

Create comprehensive test suite for the payment processing system.
Include unit tests, integration tests, and end-to-end scenarios.
Test all payment methods: credit cards, PayPal, and bank transfers.
Ensure coverage of edge cases and error handling.

labels: testing, quality-assurance, payments
```

## CLI Commands

### bot:process
Manually process a specific issue.

```bash
# Basic usage
php artisan bot:process --issue=123 --repo=owner/repo

# With specific run ID
php artisan bot:process --issue=123 --repo=owner/repo --run=custom_run_id

# Force reprocessing
php artisan bot:process --issue=123 --repo=owner/repo --force
```

### bot:status
Display status information about bot runs.

```bash
# Show recent runs across all repositories
php artisan bot:status

# Show runs for specific repository
php artisan bot:status --repo=owner/repo

# Show specific run details
php artisan bot:status --run=run_20240101_120000_abc123

# Limit number of results
php artisan bot:status --repo=owner/repo --limit=10
```

### bot:rollback
Rollback a specific run by closing all created issues.

```bash
# Rollback specific run
php artisan bot:rollback --run=run_20240101_120000_abc123

# Rollback last run for repository
php artisan bot:rollback --repo=owner/repo --last

# Force rollback (skip confirmation)
php artisan bot:rollback --run=run_20240101_120000_abc123 --force
```

## Environment Configuration

### Required Settings

```bash
# GitHub Authentication
GITHUB_APP_MODE=token                    # or 'app' for GitHub App
GITHUB_TOKEN=ghp_your_personal_token     # Required for token mode

# For GitHub App mode
GITHUB_APP_ID=your_app_id
GITHUB_PRIVATE_KEY_PATH=/path/to/key.pem
GITHUB_WEBHOOK_SECRET=your_webhook_secret
```

### AI Provider Settings

#### Gemini (Recommended)
```bash
OPENAI_PROVIDER=GEMINI
GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=gemini-2.5-flash
```

#### OpenAI
```bash
OPENAI_PROVIDER=OPENAI
OPENAI_API_KEY=sk-your-openai-key
OPENAI_MODEL=gpt-4
OPENAI_API_BASE_URL=https://api.openai.com/v1
```

#### ZAI GLM 4.6
```bash
OPENAI_PROVIDER=ZAI
OPENAI_API_KEY=your_zai_api_key
OPENAI_MODEL=zai-glm-4.6
OPENAI_API_BASE_URL=https://your-zai-endpoint.com/v1
```

### Bot Behavior Settings

```bash
# Rate limiting and limits
BOT_RUN_RATE_LIMIT_PER_MINUTE=30        # API calls per minute
BOT_MAX_ISSUES_PER_RUN=100              # Maximum issues per run

# Content filtering
BOT_ENABLE_CONTENT_FILTERING=true       # Enable prohibited content detection
BOT_REQUIRE_CONFIRMATION_THRESHOLD=50   # Require manual confirmation for high-risk content
```

## Webhook Configuration

### Setting up GitHub Webhook

1. **Navigate to Repository Settings** → **Webhooks** → **Add webhook**
2. **Configure webhook settings:**
   - **Payload URL**: `https://your-domain.com/api/webhook/github`
   - **Content type**: `application/json`
   - **Secret**: Match `GITHUB_WEBHOOK_SECRET` in `.env`
3. **Select events:**
   - ✅ Issues
   - ✅ Issue comments
4. **Set active mode** and save

### Webhook Events

| Event | Action | Bot Response |
|-------|--------|--------------|
| `issues` | `opened` | Process issue body for bot commands |
| `issues` | `edited` | Process edited issue for new commands |
| `issue_comment` | `created` | Process comment for bot commands or control commands |

## Configuration File Reference

### config/bot.php Structure

```php
return [
    'github' => [
        'mode' => env('GITHUB_APP_MODE', 'token'),
        'token' => env('GITHUB_TOKEN'),
        'app_id' => env('GITHUB_APP_ID'),
        'private_key_path' => env('GITHUB_PRIVATE_KEY_PATH'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    'openai' => [
        'provider' => env('OPENAI_PROVIDER', 'GEMINI'),
        'model' => env('OPENAI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('OPENAI_API_BASE_URL'),
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'behavior' => [
        'rate_limit_per_minute' => env('BOT_RUN_RATE_LIMIT_PER_MINUTE', 30),
        'max_issues_per_run' => env('BOT_MAX_ISSUES_PER_RUN', 100),
        'enable_content_filtering' => env('BOT_ENABLE_CONTENT_FILTERING', true),
        'require_confirmation_threshold' => env('BOT_REQUIRE_CONFIRMATION_THRESHOLD', 50),
    ],

    'commands' => [
        'mention_trigger' => '@TheOpenProducerBot',
        'trigger' => '/spawn-issues',
        'confirm' => '@TheOpenProducerBot confirm',
        'cancel' => '@TheOpenProducerBot cancel',
        'rollback' => '@TheOpenProducerBot rollback last',
        'status' => '@TheOpenProducerBot status',
    ],

    'prohibited_keywords' => [
        // Security threats
        'malware', 'ddos', 'exploit', 'vulnerability',
        // Privacy violations
        'personal data', 'credit card', 'password',
        // Spam indicators
        'click here', 'buy now', 'limited offer',
        // Hacking/illegal activities
        'hack into', 'crack password', 'steal data',
    ],
];
```

## Troubleshooting Commands

### Debug Information

```bash
# Check configuration
php artisan config:cache
php artisan config:show bot

# Check routes
php artisan route:list | grep webhook

# Check queue status
php artisan queue:failed
php artisan queue:monitor

# Test webhook endpoint
curl -X POST https://your-domain.com/api/health
```

### Log Analysis

```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log

# Filter for bot-related logs
grep "OpenProducer\|bot:\|AI Service" storage/logs/laravel.log

# Check recent errors
grep "ERROR" storage/logs/laravel.log | tail -10
```

### Database Queries

```bash
# Enter Laravel tinker
php artisan tinker

# Query recent runs
App\Models\BotRun::latest()->limit(5)->get();

# Query created issues
App\Models\BotCreatedIssue::with('run')->latest()->limit(10)->get();

# Check for failed runs
App\Models\BotRun::where('status', 'failed')->get();
```