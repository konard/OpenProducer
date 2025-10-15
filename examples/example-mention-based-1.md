# Example: Using @xierongchuan Mention (Recommended)

This example shows how to use the new mention-based approach where the bot automatically determines the optimal number of issues.

## Simple Example - Auto-determined Count

Create an issue with this content:

```markdown
@xierongchuan

Build a user authentication system with the following requirements:

**Features needed:**
- User registration with email verification
- Login/logout functionality
- Password reset via email
- JWT token-based authentication
- Session management
- OAuth integration (Google, GitHub)

**Technical requirements:**
- RESTful API design
- Secure password hashing (bcrypt)
- Rate limiting on auth endpoints
- Comprehensive unit tests
- API documentation

labels: feature, authentication, backend
```

**What happens:**
1. Bot analyzes the requirements
2. AI determines this needs ~6-8 issues (e.g., registration, login, password reset, OAuth, tests, docs)
3. Bot posts a dry-run preview showing the planned breakdown
4. You confirm with `@bot confirm`
5. Bot creates the issues automatically

## Example with Explicit Count

If you want to specify the exact count:

```markdown
@xierongchuan

template: Implement microservice for payment processing
Requirements:
- Payment gateway integration
- Transaction logging
- Webhook handling
- Retry logic for failed payments

count: 4
labels: payment, backend, microservice
components_list: gateway-integration, transaction-log, webhooks, retry-handler
```

**What this does:**
- Creates exactly 4 issues (as specified)
- Each issue will focus on one component from the list
- Labels all with "payment", "backend", "microservice"

## Complex Specification Example

```markdown
@xierongchuan

We need to build a complete CI/CD pipeline for our application:

**Pipeline stages:**
1. Code quality checks (linting, formatting)
2. Unit tests execution
3. Integration tests
4. Security scanning (SAST, dependency check)
5. Build Docker images
6. Deploy to staging environment
7. Run smoke tests
8. Deploy to production (manual approval)
9. Post-deployment monitoring setup

**Requirements:**
- Use GitHub Actions
- Support for multiple environments (dev, staging, prod)
- Automated rollback on failure
- Slack notifications for all stages
- Detailed logging and metrics

labels: devops, ci-cd, infrastructure
dry_run: true
```

**Expected result:**
- Bot analyzes the 9 stages + additional requirements
- AI determines ~12 issues are needed (one per stage + supporting tasks)
- Dry run preview shows the breakdown
- You review and confirm or adjust
