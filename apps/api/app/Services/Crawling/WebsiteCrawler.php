<?php

namespace App\Services\Crawling;

use App\DataObjects\CrawledPage;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Spatie\Crawler\Crawler;

/**
 * Wraps Spatie\Crawler\Crawler with:
 *  - SSRF protection via Guzzle middleware + CURLOPT_RESOLVE pinning
 *  - Same-domain-only enforcement
 *  - Regex-based exclude / include patterns
 *  - 1 req/s rate limit
 *  - Built-in fake mode for tests (via ->withFakes())
 *
 * Limitation: JavaScript-rendered pages are not supported. Sites that require
 * JS execution to display content will return empty or near-empty text.
 * A browser-based renderer (Puppeteer/Browsershot) is planned for a future milestone.
 */
class WebsiteCrawler
{
    private const USER_AGENT    = 'ReplyIQ-Crawler/1.0 (+https://replyiq.com/bot)';
    private const MAX_PAGES     = 50;
    private const MAX_DEPTH     = 3;
    private const DELAY_MS      = 1000; // 1 req/s
    private const MAX_BYTES     = 5 * 1024 * 1024; // 5 MB total content cap

    /** @var array<string, string>|null  URL → html body; non-null = test mode */
    private ?array $fakes = null;

    /**
     * Return a copy of this crawler pre-loaded with fake HTTP responses.
     * In fake mode, SSRF validation is skipped and no real HTTP requests are made.
     *
     * @param  array<string, string>  $fakes  URL → HTML body
     */
    public function withFakes(array $fakes): self
    {
        $clone        = clone $this;
        $clone->fakes = $fakes;

        return $clone;
    }

    /**
     * Crawl a website starting from $startUrl and return extracted page content.
     *
     * @param  array{
     *     max_pages?:        int,
     *     max_depth?:        int,
     *     include_patterns?: string[],
     *     exclude_patterns?: string[],
     * }  $options
     * @return CrawledPage[]
     *
     * @throws \App\Exceptions\SsrfBlockedException  when the start URL resolves to a blocked address.
     */
    public function crawl(string $startUrl, array $options = []): array
    {
        $maxPages        = $options['max_pages']        ?? self::MAX_PAGES;
        $maxDepth        = $options['max_depth']        ?? self::MAX_DEPTH;
        $includePatterns = $options['include_patterns'] ?? [];
        $excludePatterns = $options['exclude_patterns'] ?? [];

        // Validate the start URL against SSRF (skipped in fake/test mode).
        if ($this->fakes === null) {
            SsrfGuard::assertSafe($startUrl);
        }

        $observer = new ReplyIqCrawlObserver();
        $profile  = new ReplyIqCrawlProfile($startUrl, $includePatterns, $excludePatterns);

        $builder = Crawler::create($startUrl, $this->clientOptions())
            ->crawlProfile($profile)
            ->addObserver($observer)
            ->limit($maxPages)
            ->depth($maxDepth)
            ->delay(self::DELAY_MS)
            ->concurrency(1); // single-threaded to respect rate limit

        if ($this->fakes !== null) {
            $builder->fake($this->fakes);
        }

        $builder->start();

        return $this->buildPages($observer->getCollected());
    }

    /**
     * @param  array<int, array{url: string, html: string}>  $collected
     * @return CrawledPage[]
     */
    private function buildPages(array $collected): array
    {
        $extractor = new HtmlContentExtractor();
        $pages     = [];
        $totalBytes = 0;

        foreach ($collected as ['url' => $url, 'html' => $html]) {
            $content = $extractor->extract($html);
            $title   = $extractor->extractTitle($html);

            $totalBytes += strlen($content);

            if ($totalBytes > self::MAX_BYTES) {
                break; // hard cap reached; discard remaining pages
            }

            $pages[] = new CrawledPage($url, $title, $content);
        }

        return $pages;
    }

    /** @return array<string, mixed> */
    private function clientOptions(): array
    {
        $stack = HandlerStack::create();

        // SSRF middleware: validates every outbound URL (including redirect hops)
        // and pins the pre-validated IP via CURLOPT_RESOLVE to prevent DNS rebinding.
        $stack->push($this->ssrfMiddleware(), 'ssrf_guard');

        return [
            'handler'         => $stack,
            'timeout'         => 10,
            'connect_timeout' => 5,
            'headers'         => ['User-Agent' => self::USER_AGENT],
        ];
    }

    private function ssrfMiddleware(): callable
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
                $uri  = $request->getUri();
                $host = $uri->getHost();
                $port = $uri->getPort()
                    ?? (strtolower($uri->getScheme()) === 'https' ? 443 : 80);

                $validatedIp = SsrfGuard::assertSafe((string) $uri);

                // Pin the hostname → validated IP so curl uses it at connection
                // time, preventing DNS rebinding between our check and the socket open.
                $options['curl'][CURLOPT_RESOLVE] = ["{$host}:{$port}:{$validatedIp}"];

                return $handler($request, $options);
            };
        };
    }
}
