<?php

namespace App\Services;

use App\Models\BotCreatedIssue;
use Illuminate\Support\Facades\Log;

class DeduplicationService
{
    /**
     * Check if issue is duplicate and return hash
     */
    public function checkDuplicate(string $title, string $body, string $uniqueBy = 'hash'): array
    {
        $hash = BotCreatedIssue::generateHash($title, $body, $uniqueBy);
        $exists = BotCreatedIssue::existsByHash($hash);

        if ($exists) {
            Log::info('Duplicate issue detected', [
                'title' => $title,
                'unique_by' => $uniqueBy,
                'hash' => $hash,
            ]);
        }

        return [
            'is_duplicate' => $exists,
            'hash' => $hash,
        ];
    }

    /**
     * Filter out duplicate issues from a list
     */
    public function filterDuplicates(array $issues, string $uniqueBy = 'hash'): array
    {
        $filtered = [];
        $seenHashes = [];

        foreach ($issues as $issue) {
            $title = $issue['title'] ?? '';
            $body = $issue['body'] ?? '';

            $result = $this->checkDuplicate($title, $body, $uniqueBy);

            if (!$result['is_duplicate'] && !in_array($result['hash'], $seenHashes)) {
                $issue['hash'] = $result['hash'];
                $filtered[] = $issue;
                $seenHashes[] = $result['hash'];
            }
        }

        $duplicateCount = count($issues) - count($filtered);
        if ($duplicateCount > 0) {
            Log::info("Filtered out {$duplicateCount} duplicate issues");
        }

        return $filtered;
    }

    /**
     * Get statistics about duplicates
     */
    public function getDeduplicationStats(array $issues, string $uniqueBy = 'hash'): array
    {
        $total = count($issues);
        $unique = 0;
        $duplicates = 0;

        foreach ($issues as $issue) {
            $title = $issue['title'] ?? '';
            $body = $issue['body'] ?? '';

            $result = $this->checkDuplicate($title, $body, $uniqueBy);

            if ($result['is_duplicate']) {
                $duplicates++;
            } else {
                $unique++;
            }
        }

        return [
            'total' => $total,
            'unique' => $unique,
            'duplicates' => $duplicates,
            'unique_by' => $uniqueBy,
        ];
    }
}
