<?php

namespace App\Services\Crawling;

use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;

/**
 * Collects successfully crawled HTML pages.
 * Non-HTML responses and failed requests are silently discarded.
 */
class ReplyIqCrawlObserver extends CrawlObserver
{
    /** @var array<int, array{url: string, html: string}> */
    private array $collected = [];

    public function crawled(string $url, CrawlResponse $response, CrawlProgress $progress): void
    {
        $contentType = $response->header('Content-Type') ?? '';

        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $this->collected[] = ['url' => $url, 'html' => $response->body()];
    }

    /**
     * @return array<int, array{url: string, html: string}>
     */
    public function getCollected(): array
    {
        return $this->collected;
    }
}
