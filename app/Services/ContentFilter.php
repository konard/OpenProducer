<?php

namespace App\Services;

use Illuminate\Support\Str;

class ContentFilter
{
    private array $prohibitedKeywords;
    private bool $enabled;

    public function __construct()
    {
        $this->prohibitedKeywords = config('bot.prohibited_keywords', []);
        $this->enabled = config('bot.behavior.enable_content_filtering', true);
    }

    /**
     * Check if content contains prohibited keywords
     */
    public function containsProhibitedContent(string $content): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $content = strtolower($content);

        foreach ($this->prohibitedKeywords as $keyword) {
            if (Str::contains($content, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of found prohibited keywords
     */
    public function findProhibitedKeywords(string $content): array
    {
        if (!$this->enabled) {
            return [];
        }

        $content = strtolower($content);
        $found = [];

        foreach ($this->prohibitedKeywords as $keyword) {
            if (Str::contains($content, strtolower($keyword))) {
                $found[] = $keyword;
            }
        }

        return $found;
    }

    /**
     * Validate configuration for prohibited content
     */
    public function validateConfiguration(array $config): array
    {
        $warnings = [];

        $template = $config['template'] ?? '';
        $foundKeywords = $this->findProhibitedKeywords($template);

        if (!empty($foundKeywords)) {
            $warnings[] = 'Template contains potentially prohibited keywords: ' . implode(', ', $foundKeywords);
        }

        return $warnings;
    }

    /**
     * Check if manual confirmation is required
     */
    public function requiresConfirmation(array $config): bool
    {
        // Always require confirmation for prohibited content
        if ($this->containsProhibitedContent($config['template'] ?? '')) {
            return true;
        }

        // Require confirmation for large counts in public repos
        $threshold = config('bot.behavior.require_confirmation_threshold');
        if ($config['count'] > $threshold) {
            return true;
        }

        return false;
    }
}
