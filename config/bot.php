<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | GitHub Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for GitHub API integration. Supports both GitHub App
    | and Personal Access Token authentication.
    |
    */

    'github' => [
        'mode' => env('GITHUB_APP_MODE', 'token'), // 'app' or 'token'
        'token' => env('GITHUB_TOKEN'),
        'app_id' => env('GITHUB_APP_ID'),
        'private_key_path' => env('GITHUB_PRIVATE_KEY_PATH'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'api_version' => '2022-11-28',
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI-Compatible API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenAI-compatible API integration. Supports multiple
    | providers including ZAI GLM 4.6, OpenAI, and custom implementations.
    |
    */

    'openai' => [
        'base_url' => env('OPENAI_API_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('OPENAI_API_KEY'),
        'provider' => env('OPENAI_PROVIDER', 'ZAI'),
        'model' => env('OPENAI_MODEL', 'glm-4.6'),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 2000),
        'timeout' => 60, // seconds
        'cache_ttl' => 3600, // 1 hour cache for LLM responses
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot Behavior Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for bot behavior, rate limiting, and safety features.
    |
    */

    'behavior' => [
        'rate_limit_per_minute' => env('BOT_RUN_RATE_LIMIT_PER_MINUTE', 30),
        'max_issues_per_run' => env('BOT_MAX_ISSUES_PER_RUN', 100),
        'enable_content_filtering' => env('BOT_ENABLE_CONTENT_FILTERING', true),
        'require_confirmation_threshold' => env('BOT_REQUIRE_CONFIRMATION_THRESHOLD', 50),
        'retry_attempts' => 3,
        'retry_backoff_base' => 2, // exponential backoff: 2^attempt seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Filtering
    |--------------------------------------------------------------------------
    |
    | List of prohibited keywords and topics for content filtering.
    | Issues containing these keywords will require manual confirmation.
    |
    */

    'prohibited_keywords' => [
        // Security threats
        'malware', 'ddos', 'dos attack', 'exploit', 'vulnerability scan',
        'brute force', 'sql injection', 'xss attack', 'csrf attack',

        // Privacy violations
        'personal data', 'credit card', 'password', 'social security',
        'private key', 'api key exposed', 'leak credentials',

        // Spam indicators
        'click here', 'buy now', 'limited offer', 'act fast',

        // Hacking/illegal activities
        'hack into', 'crack password', 'bypass security', 'steal data',
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for supported bot commands.
    |
    */

    'commands' => [
        'mention_trigger' => '@xierongchuan', // New mention-based trigger
        'trigger' => '/spawn-issues', // Legacy command (kept for backwards compatibility)
        'confirm' => '@bot confirm',
        'cancel' => '@bot cancel',
        'rollback' => '@bot rollback last',
        'status' => '@bot status',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for bot run logging and storage.
    |
    */

    'logging' => [
        'store_detailed_logs' => true,
        'log_retention_days' => 30,
        'export_format' => 'json', // json, csv
    ],
];
