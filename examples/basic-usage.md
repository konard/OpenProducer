# Basic Usage Examples

## Simple Task Breakdown

### Example 1: Basic Feature Request
```
@TheOpenProducerBot

Implement user authentication system with login and signup functionality.
```

**Expected Result:** The bot will create 3-4 issues covering:
- Database schema design
- Authentication service implementation
- API endpoints creation
- Frontend login/signup forms

### Example 2: Bug Fix
```
@TheOpenProducerBot

Fix critical bug where users cannot reset their passwords.
The reset email is not being sent due to SMTP configuration issues.
```

**Expected Result:** The bot will create 2-3 issues covering:
- SMTP configuration fix
- Email template verification
- Password reset flow testing

### Example 3: Documentation Task
```
@TheOpenProducerBot

Create comprehensive documentation for our REST API endpoints.
Include authentication, user management, and data retrieval endpoints.
```

**Expected Result:** The bot will create 4-5 issues covering:
- API overview document
- Authentication documentation
- User management endpoints
- Data retrieval endpoints
- Code examples and integration guides

## With Configuration Options

### Example 4: Explicit Count and Labels
```
@TheOpenProducerBot

template: Migrate our database from MySQL to PostgreSQL
Include schema migration, data migration, and application updates.

count: 5
labels: migration, database, high-priority
```

### Example 5: Component-Based Breakdown
```
@TheOpenProducerBot

template: Implement e-commerce shopping cart functionality
Requirements:
- Add to cart functionality
- Cart persistence
- Checkout process
- Payment integration

labels: feature, e-commerce
components_list: frontend, backend, database, payment-gateway
```

## Dry Run Mode

### Example 6: Preview Before Creating
```
@TheOpenProducerBot

template: Refactor authentication module to use OAuth 2.0
Replace current JWT system with OAuth 2.0 provider integration.

labels: refactor, authentication
dry_run: true
```

**Expected Result:** The bot will post a preview comment showing what issues would be created, but won't actually create them until you confirm.