<?php

namespace App\Services\Crawling;

use Spatie\Crawler\CrawlProfiles\CrawlProfile;

/**
 * Crawl profile that enforces:
 *  - same-domain-only (no following external links)
 *  - regex-based exclude patterns (e.g., '#/login#', '#/wp-admin#')
 *  - optional regex-based include patterns (allow-list; when empty, all paths pass)
 */
class ReplyIqCrawlProfile implements CrawlProfile
{
    private string $startHost;

    /**
     * @param  string[]  $includePatterns  Regex patterns; when non-empty, only matching paths are crawled.
     * @param  string[]  $excludePatterns  Regex patterns; matching paths are always skipped.
     */
    public function __construct(
        string $startUrl,
        private readonly array $includePatterns = [],
        private readonly array $excludePatterns = [],
    ) {
        $this->startHost = (string) parse_url($startUrl, PHP_URL_HOST);
    }

    public function shouldCrawl(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';

        // Hard constraint: same domain only.
        if ($host !== $this->startHost) {
            return false;
        }

        // Exclusion check (first match wins).
        foreach ($this->excludePatterns as $pattern) {
            if (@preg_match($pattern, $path) === 1) {
                return false;
            }
        }

        // If include patterns are specified, the path must match at least one.
        if ($this->includePatterns !== []) {
            foreach ($this->includePatterns as $pattern) {
                if (@preg_match($pattern, $path) === 1) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
