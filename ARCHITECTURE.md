# OpenProducer Architecture

## Overview

OpenProducer is a Laravel-based GitHub bot that acts as an intelligent orchestrator for breaking down large specifications into manageable GitHub issues using AI. The bot operates exclusively through the GitHub Issues API and never modifies repository files.

## High-Level Architecture

```mermaid
graph TB
    subgraph "External Systems"
        GH[GitHub API]
        AI[AI Provider<br/>OpenAI/Gemini/ZAI]
    end

    subgraph "OpenProducer Bot"
        WH[Webhook Handler]
        CLI[CLI Commands]
        QUEUE[Job Queue]
        DB[(SQLite/MySQL)]
        CACHE[(Cache)]
    end

    subgraph "Core Services"
        GHC[GitHub Client]
        AIC[AI Client]
        CFG[Configuration Parser]
        FILT[Content Filter]
        DEDUP[Deduplication Service]
    end

    GH -->|Webhooks| WH
    WH --> QUEUE
    CLI --> QUEUE
    QUEUE --> GHC
    QUEUE --> AIC
    GHC --> GH
    AIC --> AI
    QUEUE --> DB
    AIC --> CACHE
    QUEUE --> CFG
    QUEUE --> FILT
    QUEUE --> DEDUP
    DEDUP --> DB
```

## System Components

### 1. Entry Points

#### 1.1 Webhook Handler
**File**: `app/Http/Controllers/IssueWebhookController.php`

The webhook handler receives GitHub webhook events and processes them.

```mermaid
sequenceDiagram
    participant GH as GitHub
    participant WH as Webhook Handler
    participant PARSER as Config Parser
    participant QUEUE as Job Queue

    GH->>WH: POST /api/webhook/github
    WH->>WH: Verify Signature
    WH->>WH: Extract Event Type

    alt Issue Event (opened/edited)
        WH->>PARSER: Parse Issue Body
        PARSER-->>WH: Configuration
        WH->>QUEUE: Dispatch ProcessSpawnIssueJob
    else Comment Event (created)
        WH->>PARSER: Extract Command
        alt Confirm Command
            WH->>QUEUE: Dispatch ProcessSpawnIssueJob (confirmed)
        else Rollback Command
            WH->>QUEUE: Dispatch RollbackRunJob
        else Status Command
            WH->>WH: Query Database
            WH-->>GH: Post Status Comment
        else Cancel Command
            WH->>WH: Update Database
            WH-->>GH: Post Cancel Comment
        end
    end

    WH-->>GH: 200 OK
```

#### 1.2 CLI Commands
**Files**:
- `app/Console/Commands/BotProcessCommand.php`
- `app/Console/Commands/BotStatusCommand.php`
- `app/Console/Commands/BotRollbackCommand.php`

CLI commands allow manual triggering and management of bot operations.

### 2. Core Services Layer

```mermaid
classDiagram
    class AiClientInterface {
        <<interface>>
        +determineIssueCount(requirements, options) int
        +generateIssueBodies(template, components, count, options) array
        +generateVariations(text, count, options) array
        +isAvailable() bool
    }

    class OpenAiCompatibleClient {
        -baseUrl: string
        -apiKey: string
        -model: string
        +determineIssueCount()
        +generateIssueBodies()
        +generateVariations()
        +isAvailable()
        -callAiApi()
        -buildPrompt()
        -fallbackGeneration()
    }

    class GeminiClient {
        -apiKey: string
        -baseUrl: string
        -model: string
        +determineIssueCount()
        +generateIssueBodies()
        +generateVariations()
        +isAvailable()
        -generateContent()
        -extractContent()
    }

    class GithubClient {
        -token: string
        -apiVersion: string
        +getIssue(owner, repo, number)
        +createIssue(owner, repo, data)
        +createComment(owner, repo, number, body)
        +updateIssue(owner, repo, number, data)
        +closeIssue(owner, repo, number)
        +listIssues(owner, repo, params)
        -withRetry(callback)
    }

    class ConfigurationParser {
        +parse(issueBody) array
        +hasTriggerCommand(issueBody) bool
        +extractCommand(commentBody) string
        -extractTemplateFromBody(issueBody)
        -parseList(value) array
        -validateConfiguration(config)
    }

    class ContentFilter {
        -prohibitedKeywords: array
        -enabled: bool
        +containsProhibitedContent(content) bool
        +findProhibitedKeywords(content) array
        +validateConfiguration(config) array
        +requiresConfirmation(config) bool
    }

    class DeduplicationService {
        +checkDuplicate(title, body, uniqueBy) array
        +filterDuplicates(issues, uniqueBy) array
        +getDeduplicationStats(issues, uniqueBy) array
    }

    AiClientInterface <|.. OpenAiCompatibleClient
    AiClientInterface <|.. GeminiClient
```

#### 2.1 AI Client Service

The AI client provides intelligent issue breakdown and generation capabilities.

**Key Features**:
- Automatic issue count determination based on complexity
- Context-aware issue generation
- Multiple provider support (OpenAI, Gemini, ZAI GLM 4.6, custom)
- Fallback mode for AI unavailability
- Response caching to reduce API calls

```mermaid
flowchart TD
    START[AI Request] --> CHECK{AI Available?}
    CHECK -->|Yes| CACHE{Cached?}
    CHECK -->|No| FALLBACK[Fallback Generation]
    CACHE -->|Yes| RETURN_CACHE[Return Cached Result]
    CACHE -->|No| API[Call AI API]
    API --> PARSE[Parse Response]
    PARSE --> STORE_CACHE[Store in Cache]
    STORE_CACHE --> RETURN[Return Result]
    FALLBACK --> RETURN
    RETURN_CACHE --> RETURN
```

#### 2.2 GitHub Client Service

Handles all GitHub API interactions with retry logic and rate limiting.

**Key Features**:
- Exponential backoff retry strategy
- Rate limit handling
- Both Personal Access Token and GitHub App authentication
- Webhook signature verification

#### 2.3 Configuration Parser

Parses bot commands and configuration from issue bodies and comments.

**Supported Formats**:
- Mention-based trigger: `@TheOpenProducerBot`
- Legacy command: `/spawn-issues`
- YAML-style configuration
- Free-form requirements (entire issue body as template)

#### 2.4 Content Filter

Security layer that filters prohibited content and enforces confirmation workflows.

**Key Features**:
- Configurable keyword filtering
- Automatic high-risk detection
- Confirmation threshold enforcement

#### 2.5 Deduplication Service

Prevents duplicate issue creation using configurable strategies.

**Strategies**:
- `title`: Hash based on issue title
- `body`: Hash based on issue body
- `hash`: Hash based on both title and body (default)

### 3. Job Processing Layer

```mermaid
stateDiagram-v2
    [*] --> Pending: Job Dispatched
    Pending --> Running: Job Started

    Running --> DryRun: dry_run=true
    Running --> ConfirmationRequired: Requires Confirmation
    Running --> Processing: Ready to Process

    DryRun --> Pending: Awaiting Confirmation
    ConfirmationRequired --> Pending: Awaiting Confirmation

    Processing --> GeneratingCount: count=null
    Processing --> GeneratingIssues: count specified
    GeneratingCount --> GeneratingIssues: AI Determined Count

    GeneratingIssues --> FilteringDuplicates
    FilteringDuplicates --> CreatingIssues

    CreatingIssues --> Completed: Success
    CreatingIssues --> Failed: Error

    Pending --> Cancelled: Cancel Command

    Completed --> [*]
    Failed --> [*]
    Cancelled --> [*]
```

#### 3.1 ProcessSpawnIssueJob

Main job that orchestrates the entire issue creation workflow.

```mermaid
flowchart TD
    START[Job Started] --> VALIDATE[Validate Repository Access]
    VALIDATE --> CHECK_FILTER[Check Content Filtering]
    CHECK_FILTER --> DRY_RUN{Dry Run or<br/>Needs Confirmation?}

    DRY_RUN -->|Yes| POST_PREVIEW[Post Preview Comment]
    POST_PREVIEW --> END[Wait for Confirmation]

    DRY_RUN -->|No| MARK_START[Mark as Started]
    MARK_START --> COUNT_CHECK{Count<br/>Specified?}

    COUNT_CHECK -->|No| AI_COUNT[AI Determine Count]
    COUNT_CHECK -->|Yes| GEN_ISSUES[Generate Issues]
    AI_COUNT --> GEN_ISSUES

    GEN_ISSUES --> FILTER[Filter Duplicates]
    FILTER --> CREATE_LOOP[Create Issues Loop]

    CREATE_LOOP --> RATE_LIMIT[Rate Limit Delay]
    RATE_LIMIT --> CREATE_ONE[Create Single Issue]
    CREATE_ONE --> STORE_DB[Store in Database]
    STORE_DB --> MORE{More Issues?}

    MORE -->|Yes| CREATE_LOOP
    MORE -->|No| POST_SUMMARY[Post Summary Comment]
    POST_SUMMARY --> MARK_COMPLETE[Mark as Completed]
    MARK_COMPLETE --> DONE[Job Completed]
```

**Process Flow**:
1. **Validation**: Check repository access
2. **Content Filtering**: Validate against prohibited keywords
3. **Dry Run/Confirmation**: Post preview if needed
4. **Count Determination**: Use AI if count not specified
5. **Issue Generation**: Generate unique issues using AI
6. **Deduplication**: Filter out duplicates
7. **Issue Creation**: Create issues on GitHub with rate limiting
8. **Summary**: Post completion summary with links

#### 3.2 RollbackRunJob

Handles rollback of created issues by closing them.

```mermaid
flowchart TD
    START[Rollback Job Started] --> FIND[Find Bot Run by run_id]
    FIND --> CHECK{Can Rollback?}

    CHECK -->|No| ERROR[Throw Exception]
    CHECK -->|Yes| GET_ISSUES[Get Created Issues]

    GET_ISSUES --> LOOP[For Each Issue]
    LOOP --> CLOSE[Close Issue on GitHub]
    CLOSE --> MARK[Mark as Deleted in DB]
    MARK --> DELAY[Rate Limit Delay]
    DELAY --> MORE{More Issues?}

    MORE -->|Yes| LOOP
    MORE -->|No| POST[Post Rollback Summary]
    POST --> DONE[Rollback Completed]

    ERROR --> FAIL[Post Error Comment]
```

### 4. Data Layer

```mermaid
erDiagram
    BotRun ||--o{ BotCreatedIssue : creates

    BotRun {
        bigint id PK
        string run_id UK "Unique run identifier"
        string repository "owner/repo"
        bigint trigger_issue_number
        string status "pending|running|completed|failed|cancelled"
        json configuration "Bot configuration"
        boolean dry_run
        boolean confirmed
        int issues_created
        int issues_planned
        text error_message
        json log_data
        timestamp started_at
        timestamp completed_at
        timestamps created_at_updated_at
    }

    BotCreatedIssue {
        bigint id PK
        string run_id FK
        string repository "owner/repo"
        bigint issue_number
        string issue_url
        string issue_title
        text issue_body
        string hash UK "Deduplication hash"
        json labels
        string status "created|deleted|failed"
        timestamps created_at_updated_at
    }
```

#### 4.1 BotRun Model

Tracks bot execution runs with complete metadata and status.

**Statuses**:
- `pending`: Waiting for confirmation or processing
- `running`: Currently executing
- `completed`: Successfully completed
- `failed`: Failed with error
- `cancelled`: Cancelled by user

**Key Methods**:
- `generateRunId()`: Creates unique run identifier (format: `run_YYYYMMDD_HHMMSS_random`)
- `markAsStarted()`: Update status to running
- `markAsCompleted()`: Update status to completed
- `markAsFailed()`: Update status to failed
- `canRollback()`: Check if rollback is possible
- `getSummary()`: Get run summary with metrics

#### 4.2 BotCreatedIssue Model

Records all issues created by the bot for tracking and rollback.

**Key Methods**:
- `generateHash()`: Create deduplication hash
- `existsByHash()`: Check if issue already exists
- `markAsDeleted()`: Mark as deleted during rollback
- `markAsFailed()`: Mark as failed if creation failed

### 5. Complete Workflow

```mermaid
sequenceDiagram
    autonumber
    participant User
    participant GitHub
    participant Webhook
    participant Queue
    participant Job
    participant ConfigParser
    participant ContentFilter
    participant AIClient
    participant Dedup
    participant GHClient
    participant Database

    User->>GitHub: Create Issue with @TheOpenProducerBot
    GitHub->>Webhook: POST /api/webhook/github (issue.opened)
    Webhook->>Webhook: Verify Signature
    Webhook->>ConfigParser: Parse Issue Body
    ConfigParser-->>Webhook: Configuration
    Webhook->>Queue: Dispatch ProcessSpawnIssueJob
    Webhook-->>GitHub: 200 OK

    Queue->>Job: Execute Job
    Job->>Database: Create BotRun (status=pending)
    Job->>GHClient: Validate Repository Access
    GHClient-->>Job: Access OK

    Job->>ContentFilter: Validate Configuration
    ContentFilter-->>Job: Warnings + Requires Confirmation

    alt Dry Run or Requires Confirmation
        Job->>GHClient: Post Preview Comment
        GHClient->>GitHub: Create Comment
        Job->>Job: Exit (wait for confirmation)

        User->>GitHub: Comment @TheOpenProducerBot confirm
        GitHub->>Webhook: POST /api/webhook/github (comment.created)
        Webhook->>ConfigParser: Extract Command
        ConfigParser-->>Webhook: "confirm"
        Webhook->>Database: Find Pending Run
        Webhook->>Queue: Dispatch ProcessSpawnIssueJob (confirmed=true)
    end

    Queue->>Job: Execute Confirmed Job
    Job->>Database: Update Status (running)

    alt Count Not Specified
        Job->>AIClient: Determine Issue Count
        AIClient->>AIClient: Check Cache
        AIClient->>AI Provider: API Call (count determination)
        AI Provider-->>AIClient: Suggested Count
        AIClient->>AIClient: Store in Cache
        AIClient-->>Job: Optimal Count
    end

    Job->>AIClient: Generate Issue Bodies
    AIClient->>AIClient: Check Cache
    AIClient->>AI Provider: API Call (issue generation)
    AI Provider-->>AIClient: Generated Issues (JSON)
    AIClient->>AIClient: Store in Cache
    AIClient-->>Job: Issue Array

    Job->>Dedup: Filter Duplicates
    Dedup->>Database: Check Existing Hashes
    Database-->>Dedup: Duplicate Info
    Dedup-->>Job: Filtered Issues

    Job->>Database: Update issues_planned

    loop For Each Issue
        Job->>GHClient: Create Issue
        GHClient->>GitHub: POST /repos/{owner}/{repo}/issues
        GitHub-->>GHClient: Issue Created
        GHClient-->>Job: Issue Data
        Job->>Database: Store BotCreatedIssue
        Job->>Database: Increment issues_created
        Job->>Job: Rate Limit Delay
    end

    Job->>GHClient: Post Summary Comment
    GHClient->>GitHub: Create Comment (with issue links)
    Job->>Database: Update Status (completed)

    opt User Requests Rollback
        User->>GitHub: Comment @TheOpenProducerBot rollback last
        GitHub->>Webhook: POST /api/webhook/github (comment.created)
        Webhook->>Database: Find Last Run
        Webhook->>Queue: Dispatch RollbackRunJob
        Queue->>RollbackJob: Execute

        loop For Each Created Issue
            RollbackJob->>GHClient: Close Issue
            GHClient->>GitHub: PATCH /repos/{owner}/{repo}/issues/{number}
            GitHub-->>GHClient: Issue Closed
            RollbackJob->>Database: Mark as Deleted
            RollbackJob->>RollbackJob: Rate Limit Delay
        end

        RollbackJob->>GHClient: Post Rollback Summary
        GHClient->>GitHub: Create Comment
    end
```

## Configuration Architecture

```mermaid
graph LR
    subgraph "Configuration Sources"
        ENV[.env File]
        BOT_CFG[config/bot.php]
        APP_CFG[config/app.php]
    end

    subgraph "Configuration Categories"
        GH_CFG[GitHub Config]
        AI_CFG[AI Config]
        BEHAVIOR[Behavior Config]
        SECURITY[Security Config]
    end

    ENV --> BOT_CFG
    ENV --> APP_CFG
    BOT_CFG --> GH_CFG
    BOT_CFG --> AI_CFG
    BOT_CFG --> BEHAVIOR
    BOT_CFG --> SECURITY

    GH_CFG --> |mode, token, webhook_secret| GHC[GitHub Client]
    AI_CFG --> |provider, model, api_key| AIC[AI Client]
    BEHAVIOR --> |rate_limit, max_issues| JOBS[Jobs]
    SECURITY --> |prohibited_keywords| FILT[Content Filter]
```

### Configuration Hierarchy

1. **GitHub Configuration** (`config/bot.php` → `github`)
   - Authentication mode (token/app)
   - API version
   - Webhook secret

2. **AI Provider Configuration** (`config/bot.php` → `openai`/`gemini`)
   - Provider selection
   - Model configuration
   - API endpoints
   - Caching settings

3. **Behavior Configuration** (`config/bot.php` → `behavior`)
   - Rate limiting
   - Maximum issues per run
   - Retry settings
   - Confirmation thresholds

4. **Security Configuration** (`config/bot.php` → `prohibited_keywords`)
   - Content filtering rules
   - Prohibited keywords list

## Security Architecture

```mermaid
graph TD
    REQUEST[Incoming Request] --> SIG_VERIFY{Webhook<br/>Signature Valid?}
    SIG_VERIFY -->|No| REJECT[Reject Request]
    SIG_VERIFY -->|Yes| PARSE[Parse Request]

    PARSE --> CONTENT_CHECK[Content Filter Check]
    CONTENT_CHECK --> PROHIBITED{Contains<br/>Prohibited<br/>Keywords?}

    PROHIBITED -->|Yes| REQUIRE_CONFIRM[Require Manual Confirmation]
    PROHIBITED -->|No| COUNT_CHECK{Count > Threshold?}

    COUNT_CHECK -->|Yes| REQUIRE_CONFIRM
    COUNT_CHECK -->|No| PROCESS[Process Automatically]

    REQUIRE_CONFIRM --> DRY_RUN[Dry Run Mode]
    DRY_RUN --> WAIT[Wait for User Confirmation]

    PROCESS --> VALIDATE[Validate Repository Access]
    VALIDATE --> SCOPE_CHECK[Check Token Scopes]
    SCOPE_CHECK --> LIMITED[Use Only Issues API]
    LIMITED --> EXECUTE[Execute Job]

    REJECT --> END[End]
    WAIT --> END
    EXECUTE --> END
```

### Security Principles

1. **No Repository File Modifications**
   - Bot only uses GitHub Issues API
   - Never performs git operations
   - No file commits or pushes

2. **Content Filtering**
   - Configurable prohibited keywords
   - Automatic malicious content detection
   - Manual confirmation for flagged content

3. **Webhook Verification**
   - HMAC signature verification
   - Secret-based authentication

4. **Rate Limiting**
   - Configurable rate limits
   - Exponential backoff on failures
   - Respects GitHub API limits

5. **Confirmation Workflow**
   - Dry run mode for preview
   - Manual confirmation for high-risk operations
   - Threshold-based automatic confirmation

## Deployment Architecture

```mermaid
graph TB
    subgraph "Deployment Options"
        WEB[Web Service + Webhooks]
        APP[GitHub App]
        CLI[CLI Mode]
        DOCKER[Docker Container]
    end

    subgraph "Required Services"
        WEB_SERVER[Web Server<br/>Laravel/Nginx]
        QUEUE_WORKER[Queue Worker<br/>php artisan queue:work]
        DB[Database<br/>SQLite/MySQL]
        CACHE_SRV[Cache<br/>Redis/File]
    end

    subgraph "External Services"
        GH_API[GitHub API]
        AI_API[AI Provider API]
    end

    WEB --> WEB_SERVER
    APP --> WEB_SERVER
    CLI --> QUEUE_WORKER
    DOCKER --> WEB_SERVER
    DOCKER --> QUEUE_WORKER

    WEB_SERVER --> QUEUE_WORKER
    QUEUE_WORKER --> DB
    QUEUE_WORKER --> CACHE_SRV
    QUEUE_WORKER --> GH_API
    QUEUE_WORKER --> AI_API
```

### Deployment Modes

1. **Web Service with Webhooks**
   - Laravel web server receives GitHub webhooks
   - Queue workers process jobs asynchronously
   - Recommended for production

2. **GitHub App**
   - OAuth App integration
   - Installation-based authentication
   - Fine-grained permissions

3. **CLI Mode**
   - Manual job triggering via artisan commands
   - Useful for testing and development
   - No webhook required

4. **Docker Container**
   - Containerized deployment
   - Includes web server and queue worker
   - Easy scaling and deployment

## Error Handling and Resilience

```mermaid
flowchart TD
    START[Operation Started] --> TRY[Try Operation]
    TRY --> SUCCESS{Successful?}

    SUCCESS -->|Yes| DONE[Complete]
    SUCCESS -->|No| CHECK_ERROR{Error Type?}

    CHECK_ERROR -->|Rate Limit| BACKOFF[Exponential Backoff]
    CHECK_ERROR -->|Server Error| RETRY_CHECK{Retry Count<br/>< Max?}
    CHECK_ERROR -->|Client Error| FAIL[Mark as Failed]

    BACKOFF --> WAIT[Wait]
    WAIT --> RETRY_CHECK

    RETRY_CHECK -->|Yes| INCREMENT[Increment Retry Count]
    RETRY_CHECK -->|No| FAIL

    INCREMENT --> TRY

    FAIL --> LOG[Log Error]
    LOG --> NOTIFY[Post Error Comment]
    NOTIFY --> UPDATE_DB[Update Database Status]
    UPDATE_DB --> END[End]

    DONE --> END
```

### Error Handling Strategy

1. **Retry with Exponential Backoff**
   - Automatic retry for transient failures
   - Configurable retry attempts (default: 3)
   - Exponential backoff (2^attempt seconds)

2. **Graceful Degradation**
   - AI service unavailable → Fallback to template duplication
   - GitHub API rate limit → Automatic backoff
   - Partial failure → Continue with successful operations

3. **Comprehensive Logging**
   - All operations logged to database
   - Detailed error messages
   - Trace information for debugging

4. **User Notification**
   - Error comments posted to trigger issue
   - Status updates in comments
   - Clear error messages

## AI Integration Architecture

```mermaid
graph TB
    subgraph "AI Providers"
        OPENAI[OpenAI GPT]
        GEMINI[Google Gemini]
        ZAI[ZAI GLM 4.6]
        CUSTOM[Custom Provider]
    end

    subgraph "AI Client Layer"
        INTERFACE[AiClientInterface]
        OAI_CLIENT[OpenAiCompatibleClient]
        GEM_CLIENT[GeminiClient]
    end

    subgraph "Operations"
        COUNT[Determine Issue Count]
        GENERATE[Generate Issues]
        VARY[Generate Variations]
    end

    subgraph "Caching Layer"
        CACHE[(Response Cache<br/>TTL: 1 hour)]
    end

    subgraph "Fallback"
        TEMPLATE[Template Duplication]
        SIMPLE[Simple Generation]
    end

    INTERFACE --> OAI_CLIENT
    INTERFACE --> GEM_CLIENT

    OAI_CLIENT --> OPENAI
    OAI_CLIENT --> ZAI
    OAI_CLIENT --> CUSTOM
    GEM_CLIENT --> GEMINI

    COUNT --> INTERFACE
    GENERATE --> INTERFACE
    VARY --> INTERFACE

    INTERFACE --> CACHE
    CACHE --> |Cache Hit| INTERFACE
    CACHE --> |Cache Miss| INTERFACE

    INTERFACE --> |AI Unavailable| FALLBACK
    FALLBACK --> TEMPLATE
    FALLBACK --> SIMPLE
```

### AI Provider Selection

The bot selects the AI client based on the `OPENAI_PROVIDER` environment variable:

- `OPENAI`: Uses OpenAI GPT models via OpenAI-compatible API
- `GEMINI`: Uses Google Gemini via native Gemini API
- `ZAI`: Uses ZAI GLM 4.6 via OpenAI-compatible API
- `CUSTOM`: Custom OpenAI-compatible provider

### AI Operations

1. **Issue Count Determination**
   - Analyzes requirement complexity
   - Considers logical task breakdown
   - Returns optimal number of issues

2. **Issue Generation**
   - Creates unique, actionable issues
   - Incorporates components naturally
   - Follows GitHub best practices

3. **Text Variations**
   - Generates variations while maintaining meaning
   - Used for title/body variations
   - Helps with deduplication

## Performance Considerations

### Caching Strategy

1. **AI Response Caching**
   - TTL: 1 hour (configurable)
   - Cache key: MD5 hash of prompt
   - Reduces API costs and latency

2. **Service Availability Caching**
   - TTL: 5 minutes
   - Prevents repeated availability checks
   - Improves response time

### Rate Limiting

1. **GitHub API**
   - Configurable requests per minute
   - Automatic backoff on 429/403 responses
   - Respects Retry-After header

2. **Issue Creation**
   - Default: 30 issues per minute
   - Configurable via `BOT_RUN_RATE_LIMIT_PER_MINUTE`
   - Per-issue delay calculation

### Queue Processing

1. **Asynchronous Job Processing**
   - Laravel queue system
   - Configurable retry attempts
   - Job timeout: 10 minutes

2. **Database Optimization**
   - Indexed columns for fast queries
   - Foreign key relationships
   - Efficient status tracking

## Monitoring and Observability

### Logging Layers

1. **Application Logs** (`storage/logs/laravel.log`)
   - All operations and errors
   - Structured logging with context
   - Debug information

2. **Database Logs** (`bot_runs` table)
   - Run metadata and status
   - Configuration snapshots
   - Timing information

3. **Issue Tracking** (`bot_created_issues` table)
   - All created issues
   - Deduplication hashes
   - Status tracking

### Health Monitoring

```mermaid
graph LR
    HEALTH[/api/health] --> CHECK_APP[Check Application]
    CHECK_APP --> CHECK_DB[Check Database]
    CHECK_DB --> CHECK_QUEUE[Check Queue]
    CHECK_QUEUE --> RESPONSE[Health Response]
```

## Extension Points

The architecture supports extensions through:

1. **New AI Providers**
   - Implement `AiClientInterface`
   - Register in `AiServiceProvider`

2. **Custom Commands**
   - Add to `IssueWebhookController`
   - Update `config/bot.php` commands

3. **Additional Filters**
   - Extend `ContentFilter`
   - Add configuration options

4. **Custom Deduplication**
   - Modify `BotCreatedIssue::generateHash()`
   - Support new strategies

## Technology Stack

- **Framework**: Laravel 11+
- **Language**: PHP 8.2+
- **Database**: SQLite / MySQL
- **Queue**: Laravel Queue (database driver)
- **Cache**: File / Redis
- **HTTP Client**: Guzzle (via Laravel HTTP)
- **AI Providers**: OpenAI, Google Gemini, ZAI GLM 4.6

## File Structure

```
OpenProducer/
├── app/
│   ├── Console/Commands/       # CLI commands
│   │   ├── BotProcessCommand.php
│   │   ├── BotStatusCommand.php
│   │   └── BotRollbackCommand.php
│   ├── Http/Controllers/       # HTTP controllers
│   │   └── IssueWebhookController.php
│   ├── Jobs/                   # Async jobs
│   │   ├── ProcessSpawnIssueJob.php
│   │   └── RollbackRunJob.php
│   ├── Models/                 # Eloquent models
│   │   ├── BotRun.php
│   │   └── BotCreatedIssue.php
│   ├── Providers/              # Service providers
│   │   ├── AppServiceProvider.php
│   │   └── AiServiceProvider.php
│   └── Services/               # Core services
│       ├── AiClientInterface.php
│       ├── OpenAiCompatibleClient.php
│       ├── GeminiClient.php
│       ├── GithubClient.php
│       ├── ConfigurationParser.php
│       ├── ContentFilter.php
│       └── DeduplicationService.php
├── config/
│   └── bot.php                 # Bot configuration
├── database/migrations/        # Database migrations
├── routes/
│   ├── api.php                # API routes (webhooks)
│   └── web.php                # Web routes
├── tests/                     # Test suite
└── storage/logs/              # Application logs
```

## Summary

OpenProducer uses a **layered architecture** with clear separation of concerns:

1. **Entry Layer**: Webhooks and CLI commands receive requests
2. **Service Layer**: Core services handle domain logic
3. **Job Layer**: Asynchronous processing with queues
4. **Data Layer**: Models and database for persistence
5. **External Layer**: GitHub API and AI provider integrations

The architecture is designed for:
- **Security**: No file modifications, content filtering, confirmation workflows
- **Resilience**: Retry logic, fallback modes, comprehensive error handling
- **Scalability**: Asynchronous processing, caching, rate limiting
- **Extensibility**: Interface-based design, provider abstraction
- **Observability**: Comprehensive logging, health monitoring, status tracking
