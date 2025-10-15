# Test Commands

This file contains ready-to-use test commands for verifying OpenProducer functionality.

## Quick Test Commands

Copy and paste these into GitHub issues to test the bot:

### Test 1: Basic Functionality
```
@TheOpenProducerBot

Create a simple "Hello World" API endpoint that returns a JSON greeting.
```

### Test 2: Configuration Options
```
@TheOpenProducerBot

template: Implement basic user registration
Requirements:
- Email validation
- Password hashing
- Account verification

count: 3
labels: feature, authentication
dry_run: true
```

### Test 3: Control Commands
After running any of the above, test these commands in comments:
```
@TheOpenProducerBot status
```
```
@TheOpenProducerBot rollback last
```

## Expected Results

1. **Test 1** should create 2-3 issues covering API design, implementation, and testing
2. **Test 2** should show a preview without creating actual issues (dry run)
3. **Control commands** should show run status and allow rollback

## CLI Test Commands

```bash
# Test configuration
php artisan config:show bot

# Test status command
php artisan bot:status

# Test webhook health
curl -X GET http://localhost:8000/api/health
```

## Verification Checklist

- [ ] Bot responds to @TheOpenProducerBot mentions
- [ ] AI provider (Gemini/OpenAI) is working
- [ ] Issues are created with appropriate titles and descriptions
- [ ] Control commands work (confirm, cancel, rollback, status)
- [ ] Rate limiting prevents API abuse
- [ ] Dry run mode shows previews without creating issues
- [ ] Webhook endpoints are accessible
- [ ] Queue worker is processing jobs
- [ ] Database tables are populated correctly
- [ ] Logs show detailed operation information