# GitHub Issue Bot (OpenProducer Meta-Bot)

> Laravel-based GitHub bot for creating multiple issues with OpenAI-compatible API support, including ZAI GLM 4.6

## 📋 Overview

This is an autonomous GitHub bot built with Laravel 10+ that creates series of issues based on templates and configurations. The bot supports OpenAI-compatible APIs for AI-powered content generation and provides features like dry-run mode, deduplication, rate limiting, and rollback capabilities.

**Key Features:**
- 🤖 Automated issue generation from templates
- 🔌 OpenAI-compatible API integration (ZAI GLM 4.6 by default)
- 🔒 Security-first: NO file modifications in target repositories
- ✅ Dry-run mode with confirmation workflow
- 🔄 Rollback support for created issues
- 🛡️ Content filtering and spam prevention
- 📊 Deduplication by title, body, or hash
- ⚡ Rate limiting and retry with exponential backoff
- 📝 Comprehensive logging and status tracking

## 🏗️ Architecture

The bot operates exclusively through GitHub Issues API - it **never modifies, commits, or pushes files** to the target repository. All operations are issue-based:
- Create issues
- Comment on issues
- Close issues (for rollback)
- Read issue content

## 📦 Requirements

- PHP 8.1 or higher
- Composer
- Laravel 10+
- SQLite or MySQL database
- GitHub Personal Access Token or GitHub App credentials
- (Optional) OpenAI-compatible API key for AI generation

## 🚀 Installation

### 1. Clone and Install Dependencies

```bash
# Clone the repository
git clone <repository-url>
cd github-issue-bot

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 2. Configure Environment

Edit `.env` file with your configuration:

```bash
# GitHub Configuration
GITHUB_APP_MODE=token
GITHUB_TOKEN=your_github_personal_access_token

# For GitHub App mode:
# GITHUB_APP_MODE=app
# GITHUB_APP_ID=your_app_id
# GITHUB_PRIVATE_KEY_PATH=/path/to/private-key.pem
# GITHUB_WEBHOOK_SECRET=your_webhook_secret

# OpenAI-Compatible API (ZAI GLM 4.6 by default)
OPENAI_API_BASE_URL=https://your-openai-compatible-api.com/v1
OPENAI_API_KEY=your_api_key
OPENAI_PROVIDER=ZAI
OPENAI_MODEL=zai-glm-4.6

# Bot Configuration
BOT_RUN_RATE_LIMIT_PER_MINUTE=30
BOT_MAX_ISSUES_PER_RUN=100
BOT_ENABLE_CONTENT_FILTERING=true
BOT_REQUIRE_CONFIRMATION_THRESHOLD=50
```

### 3. Set Up Database

```bash
# Create SQLite database
touch database/database.sqlite

# Update .env with database path
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite

# Run migrations
php artisan migrate
```

### 4. Start Queue Worker

The bot uses Laravel queues for processing jobs:

```bash
# Start queue worker
php artisan queue:work

# Or use supervisor for production (recommended)
```

## 🔧 Deployment Options

### Option 1: As a Web Service with Webhooks

1. **Deploy the application** to your server (e.g., DigitalOcean, AWS, Heroku)

2. **Configure webhook** in your GitHub repository:
   - Go to Repository Settings → Webhooks → Add webhook
   - Payload URL: `https://your-domain.com/api/webhook/github`
   - Content type: `application/json`
   - Secret: (optional, set in GITHUB_WEBHOOK_SECRET)
   - Events: Select "Issues" and "Issue comments"

3. **Start the services**:
```bash
# Start web server
php artisan serve --host=0.0.0.0 --port=8000

# Start queue worker
php artisan queue:work --tries=3
```

### Option 2: As a GitHub App

1. **Create a GitHub App**:
   - Go to GitHub Settings → Developer settings → GitHub Apps → New GitHub App
   - Webhook URL: `https://your-domain.com/api/webhook/github`
   - Permissions: Issues (Read & Write), Metadata (Read)
   - Subscribe to events: Issues, Issue comment

2. **Configure .env**:
```bash
GITHUB_APP_MODE=app
GITHUB_APP_ID=your_app_id
GITHUB_PRIVATE_KEY_PATH=/path/to/private-key.pem
GITHUB_WEBHOOK_SECRET=your_webhook_secret
```

3. **Install the app** on your target repositories

### Option 3: Using Docker Compose

See `docker-compose.yml` for containerized deployment.

```bash
docker-compose up -d
```

### Option 4: CLI Mode (Manual Trigger)

Process issues manually without webhooks:

```bash
php artisan bot:process --issue=123 --repo=owner/repo
```

## 📖 Usage

### Creating Issues with /spawn-issues Command

Create an issue in your repository with the following format:

```
/spawn-issues
count: 10
template: Fix bug in authentication module
This needs to be addressed urgently
labels: bug, high-priority, auto-agent-task
assignees: username1, username2
dry_run: true
unique_by: title
components_list: login, signup, password-reset
```

**Configuration Options:**

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `count` | integer | Number of issues to create | 1 |
| `template` | string (multiline) | Template for issue body | Required |
| `labels` | comma-separated | Labels to apply | [] |
| `assignees` | comma-separated | Users to assign | [] |
| `dry_run` | boolean | Preview without creating | false |
| `unique_by` | title/body/hash | Deduplication strategy | hash |
| `components_list` | comma-separated | Components to include | [] |
| `rate_limit_per_minute` | integer | Rate limit for API calls | 30 |

### Dry Run and Confirmation Workflow

1. Create issue with `dry_run: true`
2. Bot posts preview comment with configuration
3. Reply with `@bot confirm` to proceed
4. Bot creates issues and posts summary

### Bot Commands

Reply to the bot's comment with these commands:

| Command | Description |
|---------|-------------|
| `@bot confirm` | Confirm and proceed with issue creation |
| `@bot cancel` | Cancel pending run |
| `@bot rollback last` | Rollback last run (closes all created issues) |
| `@bot status` | Show status of recent runs |

### Artisan Commands

```bash
# Process a specific issue manually
php artisan bot:process --issue=123 --repo=owner/repo

# Rollback a run
php artisan bot:rollback --run=run_20240101_120000_abc123

# Show status of runs
php artisan bot:status
php artisan bot:status --run=run_20240101_120000_abc123
php artisan bot:status --repo=owner/repo --limit=20
```

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
php artisan test

# Run specific test suite
./vendor/bin/phpunit tests/Unit/ConfigurationParserTest.php
./vendor/bin/phpunit tests/Unit/DeduplicationServiceTest.php
```

## 🔌 OpenAI-Compatible API Integration

### ZAI GLM 4.6 (Default)

The bot comes pre-configured for ZAI GLM 4.6:

```bash
OPENAI_PROVIDER=ZAI
OPENAI_MODEL=zai-glm-4.6
OPENAI_API_BASE_URL=https://your-zai-endpoint.com/v1
OPENAI_API_KEY=your_zai_api_key
```

**Example API Request:**
```json
{
  "model": "zai-glm-4.6",
  "messages": [
    {
      "role": "user",
      "content": "Generate issue descriptions..."
    }
  ],
  "temperature": 0.7,
  "max_tokens": 2000
}
```

### Using Other Providers

The bot supports any OpenAI-compatible API:

**OpenAI:**
```bash
OPENAI_PROVIDER=OPENAI
OPENAI_MODEL=gpt-4
OPENAI_API_BASE_URL=https://api.openai.com/v1
OPENAI_API_KEY=sk-...
```

**Custom Provider:**
```bash
OPENAI_PROVIDER=CUSTOM
OPENAI_MODEL=your-model-name
OPENAI_API_BASE_URL=https://your-api.com/v1
OPENAI_API_KEY=your_key
```

### Fallback Mode

If AI API is unavailable, the bot automatically falls back to simple template duplication with component rotation.

## 🛡️ Security Features

### No Repository File Modifications

The bot is designed to **NEVER** modify files in the repository:
- No git operations (clone, commit, push)
- Only GitHub Issues API calls
- Logged warning if token has write access

### Content Filtering

The bot filters prohibited content (configurable in `config/bot.php`):
- Malware, DDoS, exploits
- Personal data, credentials
- Spam indicators

Issues with prohibited keywords require manual confirmation.

### Rate Limiting

- Configurable rate limit per minute
- Automatic retry with exponential backoff
- Respects GitHub API rate limits

### Webhook Signature Verification

Set `GITHUB_WEBHOOK_SECRET` to verify webhook authenticity.

## 📊 Logging and Monitoring

### Database Logs

All runs are logged in the database:
- `bot_runs` table: Run metadata and status
- `bot_created_issues` table: Created issues with deduplication hashes

### Application Logs

Check `storage/logs/laravel.log` for detailed operation logs.

### Health Check

```bash
curl https://your-domain.com/api/health
```

## 🐛 Troubleshooting

### Issue: Bot doesn't respond to /spawn-issues

1. Check webhook is configured correctly
2. Verify `GITHUB_TOKEN` has `issues` scope
3. Check queue worker is running: `php artisan queue:work`
4. Review logs: `storage/logs/laravel.log`

### Issue: AI generation fails

1. Verify `OPENAI_API_KEY` and `OPENAI_API_BASE_URL`
2. Test API endpoint manually
3. Bot will fallback to simple template mode

### Issue: Rate limit errors

1. Reduce `BOT_RUN_RATE_LIMIT_PER_MINUTE`
2. Check GitHub API rate limit: https://api.github.com/rate_limit

## 📝 Example Scenarios

### Scenario 1: Create 50 bug fix tasks

```
/spawn-issues
count: 50
template: Fix critical bug in production
Impact: High
Priority: P0
labels: bug, critical, needs-triage
dry_run: false
unique_by: hash
```

### Scenario 2: Generate component-specific tests

```
/spawn-issues
count: 5
template: Write unit tests for component
Coverage target: 80%
labels: testing, enhancement
components_list: auth, api, database, ui, logging
unique_by: title
```

### Scenario 3: Create documentation tasks

```
/spawn-issues
count: 10
template: Update documentation for new feature
Include code examples and usage instructions
labels: documentation, good-first-issue
dry_run: true
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch
3. Write tests for new features
4. Submit a pull request

## 📄 License

MIT License - see LICENSE file for details

## 🙏 Acknowledgments

- Laravel Framework
- GitHub API
- ZAI GLM 4.6
- OpenAI-compatible ecosystem

## 📞 Support

For issues and questions:
- GitHub Issues: [repository issues]
- Documentation: This README
- Logs: Check `storage/logs/laravel.log`

---

**⚠️ Important Security Notice:**

This bot is designed to operate ONLY through GitHub Issues API. It will NEVER:
- Modify files in your repository
- Create commits
- Push changes
- Perform any git operations

All functionality is issue-based for maximum safety and transparency.
