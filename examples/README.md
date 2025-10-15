# OpenProducer Examples

This directory contains comprehensive examples and guides for using the OpenProducer GitHub bot effectively.

## 📁 Contents

- **[basic-usage.md](./basic-usage.md)** - Simple examples for getting started quickly
- **[advanced-usage.md](./advanced-usage.md)** - Complex scenarios and sophisticated use cases
- **[command-reference.md](./command-reference.md)** - Complete command and configuration reference

## 🚀 Quick Start

### 1. Simple Task Breakdown
Create an issue with:
```
@TheOpenProducerBot

Build a user authentication system with login and signup functionality.
```

### 2. Complex Project
```
@TheOpenProducerBot

Design microservices architecture for e-commerce platform:
- User service (auth, profiles)
- Product service (catalog, inventory)
- Order service (cart, checkout)
- Notification service (email, SMS)

labels: architecture, microservices
components_list: user-service, product-service, order-service, notification-service
```

### 3. Control Commands
After bot creates issues, you can:
- `@TheOpenProducerBot rollback last` - Undo last run
- `@TheOpenProducerBot status` - Check run status
- `@TheOpenProducerBot confirm` - Confirm dry run

## 📖 Example Categories

### Basic Examples ([basic-usage.md](./basic-usage.md))
- Simple feature requests
- Bug fixes
- Documentation tasks
- Configuration options
- Dry run mode

### Advanced Examples ([advanced-usage.md](./advanced-usage.md))
- Microservices architecture
- Mobile app development
- DevOps pipeline setup
- AI feature integration
- Performance optimization
- Security audits

### Command Reference ([command-reference.md](./command-reference.md))
- Complete command syntax
- Configuration options
- CLI commands
- Environment setup
- Troubleshooting

## 🎯 Best Practices

### 1. Clear Requirements
```
@TheOpenProducerBot

Implement user authentication with:
- JWT token support
- Password reset via email
- Rate limiting (5 attempts per minute)
- Session management
- OAuth 2.0 integration optional

Requirements: Must integrate with existing PostgreSQL database.
```

### 2. Use Components for Complex Systems
```
@TheOpenProducerBot

Build e-commerce platform with complete functionality.

components_list: frontend, backend-api, database, payment-gateway, email-service, admin-panel
labels: e-commerce, fullstack
```

### 3. Dry Run for Complex Tasks
```
@TheOpenProducerBot

Complete refactor of legacy monolith to microservices.
Include database migration, API redesign, and deployment strategy.

dry_run: true
labels: refactor, architecture, high-risk
```

### 4. Appropriate Labeling
```
@TheOpenProducerBot

Fix critical production bug in payment processing.

labels: bug, critical, production, payments
priority: P0
```

## 🔧 Configuration Examples

### Environment Setup
```bash
# Gemini AI (Recommended)
OPENAI_PROVIDER=GEMINI
GEMINI_API_KEY=your_gemini_key
GEMINI_MODEL=gemini-2.5-flash

# GitHub Token
GITHUB_APP_MODE=token
GITHUB_TOKEN=ghp_your_token

# Bot Behavior
BOT_RUN_RATE_LIMIT_PER_MINUTE=30
BOT_MAX_ISSUES_PER_RUN=100
```

### Advanced Configuration
```php
// config/bot.php
'behavior' => [
    'rate_limit_per_minute' => 30,
    'max_issues_per_run' => 100,
    'enable_content_filtering' => true,
    'require_confirmation_threshold' => 50,
],
```

## 📊 Real-World Scenarios

### Scenario 1: Sprint Planning
```
@TheOpenProducerBot

Plan upcoming 2-week sprint for mobile app development:
- Implement user profile editing
- Add push notification settings
- Create offline data synchronization
- Improve app performance by 30%
- Write unit tests for new features

Focus on user experience improvements and performance.

labels: sprint, mobile, performance
count: 8
```

### Scenario 2: Technical Debt Reduction
```
@TheOpenProducerBot

Address technical debt in authentication module:
- Update deprecated dependencies
- Refactor legacy authentication logic
- Improve error handling and logging
- Add comprehensive test coverage
- Update documentation

Priority: Reduce complexity and improve maintainability.

labels: tech-debt, refactor, quality
components_list: dependencies, code-refactor, testing, documentation
```

### Scenario 3: Security Implementation
```
@TheOpenProducerBot

Implement comprehensive security measures:
- Add input validation and sanitization
- Implement rate limiting
- Set up security headers
- Add audit logging
- Create security incident response plan

Must comply with OWASP security standards.

labels: security, compliance, owasp
dry_run: true
```

## 🚨 Common Pitfalls to Avoid

### 1. Vague Requirements
❌ **Bad:**
```
@TheOpenProducerBot

Fix the app
```

✅ **Good:**
```
@TheOpenProducerBot

Fix login issue where users with special characters in passwords cannot authenticate.
Error occurs in authentication service, affects 5% of user base.
```

### 2. Overly Large Tasks
❌ **Bad:**
```
@TheOpenProducerBot

Build entire social media platform
```

✅ **Good:**
```
@TheOpenProducerBot

Implement user authentication and profile management for social platform.
Focus on core auth features, profile creation, and basic privacy settings.

labels: auth, profiles, social
components_list: authentication, profiles, privacy
```

### 3. Missing Context
❌ **Bad:**
```
@TheOpenProducerBot

Add new API endpoints
```

✅ **Good:**
```
@TheOpenProducerBot

Add REST API endpoints for user management:
- GET /api/users (list with pagination)
- GET /api/users/{id} (user details)
- PUT /api/users/{id} (update profile)
- DELETE /api/users/{id} (deactivate account)

Must use JWT authentication and follow RESTful principles.

labels: api, backend, users
```

## 🔄 Workflow Integration

### 1. Sprint Planning
Use OpenProducer to break down epics into manageable sprint tasks.

### 2. Bug Triage
Quickly create structured issues for bug reports with proper classification.

### 3. Documentation
Generate comprehensive documentation tasks for new features.

### 4. Technical Debt
Systematically address technical debt with structured refactoring tasks.

### 5. Onboarding
Create structured tasks for new team members to understand the codebase.

## 📞 Getting Help

- **Documentation**: Check [command-reference.md](./command-reference.md) for detailed syntax
- **Examples**: Review [basic-usage.md](./basic-usage.md) and [advanced-usage.md](./advanced-usage.md)
- **Troubleshooting**: See the Troubleshooting section in [command-reference.md](./command-reference.md)
- **Issues**: Report problems or request features in the main repository

---

**Pro Tip**: Start with dry run mode (`dry_run: true`) for complex tasks to preview the breakdown before creating actual issues!