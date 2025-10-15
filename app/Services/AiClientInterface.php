<?php

namespace App\Services;

interface AiClientInterface
{
    /**
     * Determine optimal number of issues based on requirements
     *
     * @param string $requirements The requirements/specification text
     * @param array $options Additional options
     * @return int Recommended number of issues to create
     */
    public function determineIssueCount(string $requirements, array $options = []): int;

    /**
     * Generate issue bodies based on template and components
     *
     * @param string $template The template to use for generation
     * @param array $componentsList List of components/variations
     * @param int $count Number of issues to generate
     * @param array $options Additional options for generation
     * @return array Generated issue data
     */
    public function generateIssueBodies(
        string $template,
        array $componentsList,
        int $count,
        array $options = []
    ): array;

    /**
     * Generate variations of a given text
     *
     * @param string $text The text to generate variations for
     * @param int $count Number of variations to generate
     * @param array $options Additional options
     * @return array Generated variations
     */
    public function generateVariations(string $text, int $count, array $options = []): array;

    /**
     * Check if the AI service is available
     *
     * @return bool
     */
    public function isAvailable(): bool;
}
