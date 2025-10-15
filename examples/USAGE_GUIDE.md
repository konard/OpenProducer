# Usage Guide: GitHub Issue Bot

## Quick Start

### Step 1: Set Up the Bot

1. Deploy the bot (see README.md for deployment options)
2. Configure webhook in your GitHub repository
3. Ensure queue worker is running

### Step 2: Create a Spawn Issue

Create a new issue in your repository with the `/spawn-issues` command:

```markdown
/spawn-issues
count: 5
template: Your issue template here
labels: auto-task, bug
dry_run: true
```

### Step 3: Review and Confirm

1. Bot will post a preview comment
2. Review the configuration
3. Reply with `@bot confirm` to proceed
4. Bot creates issues and posts summary

## Command Reference

### /spawn-issues Configuration

```yaml
/spawn-issues
count: <number>                    # Required: How many issues to create
template: <multiline text>          # Required: Template for issue body
labels: <comma-separated>           # Optional: Labels to apply
assignees: <comma-separated>        # Optional: Users to assign
dry_run: <true|false>              # Optional: Preview mode (default: false)
unique_by: <title|body|hash>       # Optional: Deduplication method
components_list: <comma-separated>  # Optional: Components to include
rate_limit_per_minute: <number>    # Optional: API rate limit
```

### Bot Commands (in comments)

| Command | Description | Example |
|---------|-------------|---------|
| `@bot confirm` | Confirm and create issues | Reply to preview comment |
| `@bot cancel` | Cancel pending run | Reply to preview comment |
| `@bot rollback last` | Close all issues from last run | Reply to any bot comment |
| `@bot status` | Show recent run statuses | Reply to any bot comment |

## Common Workflows

### Workflow 1: Safe Issue Creation (Recommended)

```markdown
/spawn-issues
count: 20
template: Refactor legacy code module
Focus on improving maintainability and test coverage
labels: refactor, technical-debt
dry_run: true
```

1. Create issue with `dry_run: true`
2. Bot posts preview
3. Review preview carefully
4. Reply `@bot confirm` if satisfied
5. Bot creates issues

### Workflow 2: Bulk Task Distribution

```markdown
/spawn-issues
count: 10
template: Implement feature X for platform
Each team member should tackle one platform
labels: enhancement, help-wanted
components_list: iOS, Android, Web, Desktop, API, Mobile, Backend, Frontend, DevOps, QA
unique_by: title
dry_run: false
```

Creates 10 issues immediately, one per component.

### Workflow 3: AI-Powered Variations

```markdown
/spawn-issues
count: 30
template: Write integration test for API endpoint
Include positive and negative test cases
Mock external services
labels: testing, api
dry_run: true
```

With AI configured:
1. Bot generates 30 unique test scenarios
2. Preview shows variations
3. Confirm to create

### Workflow 4: Rollback Mistakes

If you created issues by mistake:

```markdown
@bot rollback last
```

Bot will close all issues from the most recent run.

## Advanced Usage

### Custom Rate Limiting

For repositories with strict rate limits:

```markdown
/spawn-issues
count: 100
template: Migrate component to new framework
labels: migration
rate_limit_per_minute: 10
dry_run: false
```

Creates 100 issues at 10 per minute (takes ~10 minutes).

### Deduplication Strategies

**By Title:**
```markdown
unique_by: title
```
Prevents creating issues with identical titles.

**By Body:**
```markdown
unique_by: body
```
Prevents creating issues with identical content.

**By Hash (default):**
```markdown
unique_by: hash
```
Prevents creating issues with identical title AND body.

### Using with Components

```markdown
/spawn-issues
count: 3
template: Optimize performance in component
Target: 50% improvement
components_list: Database, API, Frontend
```

Bot creates:
1. "Optimize performance in Database"
2. "Optimize performance in API"
3. "Optimize performance in Frontend"

## Artisan CLI Usage

### Process Issue Manually

```bash
php artisan bot:process --issue=123 --repo=owner/repo
```

### Check Run Status

```bash
# Show recent runs
php artisan bot:status

# Show specific run
php artisan bot:status --run=run_20240101_120000_abc123

# Filter by repository
php artisan bot:status --repo=owner/repo --limit=20
```

### Rollback via CLI

```bash
php artisan bot:rollback --run=run_20240101_120000_abc123
```

## Troubleshooting

### Bot Not Responding

**Check 1:** Webhook configured?
```bash
# Test webhook endpoint
curl -X POST https://your-domain.com/api/webhook/github
```

**Check 2:** Queue worker running?
```bash
php artisan queue:work
```

**Check 3:** Logs
```bash
tail -f storage/logs/laravel.log
```

### AI Generation Not Working

Bot falls back to simple template duplication if AI fails. Check:

```bash
# Verify AI configuration in .env
OPENAI_API_BASE_URL=...
OPENAI_API_KEY=...
OPENAI_MODEL=...
```

Test AI availability:
```php
$aiClient = app(App\Services\AiClientInterface::class);
$available = $aiClient->isAvailable();
```

### Rate Limit Errors

Reduce rate limit:
```markdown
rate_limit_per_minute: 10
```

Or split into multiple smaller runs.

## Best Practices

1. **Always use dry_run first** for large counts (>10)
2. **Set appropriate labels** for easy filtering
3. **Use components_list** for distributing work
4. **Test with count: 1** before scaling up
5. **Monitor rate limits** in GitHub settings
6. **Review created issues** before assigning to team
7. **Keep templates clear and actionable**
8. **Use unique_by** to prevent duplicates

## Security Notes

- Bot never modifies repository files
- Only creates/closes issues via API
- Content filtering prevents prohibited keywords
- Confirmation required for large batches (>50)
- All operations are logged and auditable

## Examples by Use Case

### Bug Tracking
```markdown
/spawn-issues
count: 20
template: Critical bug in production - needs immediate attention
labels: bug, critical, P0
dry_run: true
```

### Sprint Planning
```markdown
/spawn-issues
count: 15
template: Sprint task - implement feature X
labels: sprint-2024-Q1, enhancement
assignees: dev-team
```

### Documentation Sprint
```markdown
/spawn-issues
count: 25
template: Document API endpoint
labels: documentation, good-first-issue
components_list: users, posts, comments, auth, payments
```

### Code Review Tasks
```markdown
/spawn-issues
count: 10
template: Code review required for module
labels: review-needed, code-quality
assignees: senior-dev-1, senior-dev-2
```

---

For more information, see the main [README.md](../README.md)
