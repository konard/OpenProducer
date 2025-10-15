# OpenProducer - GitHub Issue Orchestrator Bot

> Laravel-based GitHub bot that intelligently breaks down large specifications into manageable issues using AI

## 📋 Overview

OpenProducer is an autonomous GitHub bot built with Laravel 11+ that serves as a **meta-bot** or **orchestrator** for development teams. Simply mention `@TheOpenProducerBot` in an issue with your requirements, and the bot will intelligently break down large specifications into smaller, actionable issues.

The bot supports multiple AI providers (OpenAI, Gemini, ZAI GLM 4.6, and any OpenAI-compatible API) to analyze requirements and automatically determine the optimal task breakdown.

**Key Features:**
- 🎯 **Intelligent task orchestration**: Automatically breaks down large specs into manageable issues
- 🧠 **AI-powered analysis**: Determines optimal number of sub-tasks based on complexity
- 🚀 **Simple mention-based triggering**: Just use `@TheOpenProducerBot` - no complex commands needed
- 🔌 Multiple AI providers: OpenAI, Gemini, ZAI GLM 4.6, and any OpenAI-compatible API
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

- PHP 8.2 or higher
- Composer
- Laravel 11+
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

# AI Provider Configuration
# Choose one: OPENAI, GEMINI, ZAI, CUSTOM
OPENAI_PROVIDER=GEMINI
OPENAI_MODEL=gemini-2.5-flash

# OpenAI Configuration
OPENAI_API_BASE_URL=https://api.openai.com/v1
OPENAI_API_KEY=your_openai_api_key

# Gemini Configuration
GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=gemini-2.5-flash

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

For comprehensive examples and detailed guides, see the [examples/](./examples/) directory.

### Creating Issues with @TheOpenProducerBot Mention (Recommended)

Simply mention `@TheOpenProducerBot` in an issue with your requirements, and the bot will automatically:
1. Analyze your requirements
2. Determine the optimal number of sub-issues needed
3. Break down the specification into actionable tasks
4. Create the issues

**Simple Example:**
```
@TheOpenProducerBot

Build a user authentication system with login, signup, password reset, and email verification.
The system should use JWT tokens and integrate with our existing API.
```

**With Optional Configuration:**
```
@TheOpenProducerBot

template: Implement user authentication feature
Requirements:
- JWT token-based authentication
- Login, signup, password reset flows
- Email verification
- Integration with existing API

labels: feature, backend
dry_run: true
components_list: auth-service, email-service, api-gateway
```

**Configuration Options:**

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `count` | integer | Number of issues to create | AI-determined based on complexity |
| `template` | string (multiline) | Template/requirements for issues | Required (or entire issue body) |
| `labels` | comma-separated | Labels to apply | [] |
| `assignees` | comma-separated | Users to assign | [] |
| `dry_run` | boolean | Preview without creating | false |
| `unique_by` | title/body/hash | Deduplication strategy | hash |
| `components_list` | comma-separated | Components to include | [] |
| `rate_limit_per_minute` | integer | Rate limit for API calls | 30 |

**Note:** The `count` parameter is now **optional**. If not specified, the AI will analyze your requirements and automatically determine the optimal number of issues to create.

### Legacy /spawn-issues Command (Still Supported)

The original `/spawn-issues` command format is still supported for backwards compatibility:

```
/spawn-issues
count: 10
template: Fix bug in authentication module
labels: bug, high-priority
```

### Dry Run and Confirmation Workflow

1. Create issue with `dry_run: true`
2. Bot posts preview comment with configuration
3. Reply with `@TheOpenProducerBot confirm` to proceed
4. Bot creates issues and posts summary

### Bot Commands

Reply to the bot's comment with these commands:

| Command | Description |
|---------|-------------|
| `@TheOpenProducerBot confirm` | Confirm and proceed with issue creation |
| `@TheOpenProducerBot cancel` | Cancel pending run |
| `@TheOpenProducerBot rollback last` | Rollback last run (closes all created issues) |
| `@TheOpenProducerBot status` | Show status of recent runs |

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

## 🔌 AI Provider Integration

OpenProducer supports multiple AI providers through a unified interface. The bot will automatically determine the optimal number of issues based on your requirements using the configured AI service.

### Gemini (Recommended)

Google's Gemini API offers excellent performance for task breakdown:

```bash
OPENAI_PROVIDER=GEMINI
GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=gemini-2.5-flash
```

### OpenAI

OpenAI's GPT models provide high-quality task analysis:

```bash
OPENAI_PROVIDER=OPENAI
OPENAI_API_KEY=sk-your-openai-key
OPENAI_MODEL=gpt-4
OPENAI_API_BASE_URL=https://api.openai.com/v1
```

### ZAI GLM 4.6

ZAI's GLM 4.6 model is optimized for development tasks:

```bash
OPENAI_PROVIDER=ZAI
OPENAI_API_KEY=your_zai_api_key
OPENAI_MODEL=zai-glm-4.6
OPENAI_API_BASE_URL=https://your-zai-endpoint.com/v1
```

### Custom OpenAI-Compatible Provider

Any OpenAI-compatible API can be used:

```bash
OPENAI_PROVIDER=CUSTOM
OPENAI_API_KEY=your_api_key
OPENAI_MODEL=your-model-name
OPENAI_API_BASE_URL=https://your-api.com/v1
```

### Fallback Mode

If the AI API is unavailable, the bot automatically falls back to simple template duplication with component rotation.

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

### Scenario 1: Break down large specification (AI-determined count)

```
@TheOpenProducerBot

We need to implement a complete REST API for our e-commerce platform with the following features:
- User authentication and authorization (JWT)
- Product catalog management (CRUD operations)
- Shopping cart functionality
- Order processing and payment integration
- Admin dashboard for analytics
- Email notifications for order status

The API should follow RESTful principles, include comprehensive error handling, and have full test coverage.

labels: api, backend, feature
```

The bot will analyze this and automatically determine that this requires approximately 8-10 issues covering different aspects.

### Scenario 2: Simple task with explicit count

```
@TheOpenProducerBot

template: Fix critical production bug in authentication module
Impact: Users cannot log in
Priority: P0

count: 3
labels: bug, critical, p0
components_list: auth-service, session-manager, api-gateway
```

### Scenario 3: Documentation tasks (auto-determined)

```
@TheOpenProducerBot

Update documentation for the new API endpoints we added in v2.0:
- Authentication endpoints
- User management
- Product catalog
- Orders and payments
- Webhooks

Each section needs API reference, code examples, and integration guide.

labels: documentation, v2.0
unique_by: title
```

### Scenario 4: Using legacy command format

```
/spawn-issues
count: 5
template: Write unit tests for component
Coverage target: 80%
labels: testing, enhancement
components_list: auth, api, database, ui, logging
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
