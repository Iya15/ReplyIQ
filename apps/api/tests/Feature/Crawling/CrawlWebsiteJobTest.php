<?php

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Chatbot;
use App\Models\Document;
use App\Models\Organization;
use App\Services\Crawling\WebsiteCrawler;
use App\Services\Embedding\EmbeddingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeCrawlChatbot(): array
{
    $org     = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create();
    app()->instance('currentOrganization', $org);

    return [$org, $chatbot];
}

function makeCrawlDocument(string $orgId, string $chatbotId, string $url = 'https://example.com'): Document
{
    return Document::create([
        'organization_id' => $orgId,
        'chatbot_id'      => $chatbotId,
        'source_type'     => DocumentSourceType::Url,
        'source_url'      => $url,
        'title'           => 'example.com',
        'status'          => DocumentStatus::Pending,
        'metadata'        => ['max_pages' => 10],
    ]);
}

function crawlFakeEmbedder(): EmbeddingClient
{
    return new class implements EmbeddingClient {
        public function embed(string $text): array { return array_fill(0, 1536, 0.1); }

        public function embedBatch(array $texts): array
        {
            return array_map(fn () => array_fill(0, 1536, 0.1), $texts);
        }

        public function dimension(): int { return 1536; }

        public function model(): string { return 'fake'; }
    };
}

function htmlPage(string $title, string $body): string
{
    return "<html><head><title>{$title}</title></head><body><main>{$body}</main></body></html>";
}

function runCrawlJob(Document $document): void
{
    app()->instance(EmbeddingClient::class, crawlFakeEmbedder());
    app()->call([new \App\Jobs\CrawlWebsiteJob($document), 'handle']);
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('processes a multi-page mock site into a ready document with chunks', function () {
    [$org, $chatbot] = makeCrawlChatbot();
    $document        = makeCrawlDocument($org->id, $chatbot->id);

    $fakes = [
        'https://example.com'          => htmlPage('Home',    'Welcome to Acme. We help teams work better.'),
        'https://example.com/about'    => htmlPage('About',   'Founded in 2020, Acme Corp builds collaboration tools.'),
        'https://example.com/pricing'  => htmlPage('Pricing', 'Starter plan is $29 per month. Business plan is $99.'),
        'https://example.com/contact'  => htmlPage('Contact', 'Reach us at hello@acme.com or call 555-0100.'),
        'https://example.com/features' => htmlPage('Features', 'Integrations with Slack, Jira, and GitHub.'),
    ];

    app()->instance(WebsiteCrawler::class, (new WebsiteCrawler())->withFakes($fakes));

    runCrawlJob($document);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->chunk_count)->toBeGreaterThan(0)
        ->and($document->char_count)->toBeGreaterThan(0)
        ->and($document->metadata['page_count'])->toBeGreaterThan(0)
        ->and($document->metadata['crawled_urls'])->not->toBeEmpty();
});

it('skips pages with content below the minimum character threshold', function () {
    [$org, $chatbot] = makeCrawlChatbot();
    $document        = makeCrawlDocument($org->id, $chatbot->id);

    $fakes = [
        'https://example.com'       => htmlPage('Home',  'This is real content with enough characters to pass the threshold check.'),
        'https://example.com/empty' => htmlPage('Empty', 'Too short.'), // < 100 chars after extraction
    ];

    app()->instance(WebsiteCrawler::class, (new WebsiteCrawler())->withFakes($fakes));

    runCrawlJob($document);

    $document->refresh();

    // Only the home page should be counted; the empty page is below threshold.
    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->metadata['page_count'])->toBe(1);
});

it('deduplicates pages with identical content', function () {
    [$org, $chatbot] = makeCrawlChatbot();
    $document        = makeCrawlDocument($org->id, $chatbot->id);

    $identicalContent = 'This is exactly the same content on both pages. '.
        'It has enough characters to pass the minimum threshold for crawling.';

    $fakes = [
        'https://example.com'      => htmlPage('Page A', $identicalContent),
        'https://example.com/dup'  => htmlPage('Page B', $identicalContent), // same body
    ];

    app()->instance(WebsiteCrawler::class, (new WebsiteCrawler())->withFakes($fakes));

    runCrawlJob($document);

    $document->refresh();

    // Deduplication by hash: only one of the two identical pages should count.
    expect($document->metadata['page_count'])->toBe(1);
});

it('sets status to failed when crawl yields no usable content', function () {
    [$org, $chatbot] = makeCrawlChatbot();
    $document        = makeCrawlDocument($org->id, $chatbot->id);

    // All pages below threshold.
    $fakes = [
        'https://example.com' => htmlPage('Empty', 'Hi.'),
    ];

    app()->instance(WebsiteCrawler::class, (new WebsiteCrawler())->withFakes($fakes));

    // Job throws on failure but we catch it here to inspect state.
    try {
        runCrawlJob($document);
    } catch (\Throwable) {}

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->not->toBeEmpty();
});

it('stores crawled_urls and page_count in document metadata', function () {
    [$org, $chatbot] = makeCrawlChatbot();
    $document        = makeCrawlDocument($org->id, $chatbot->id);

    $fakes = [
        'https://example.com'      => htmlPage('Home',    str_repeat('Useful content. ', 20)),
        'https://example.com/docs' => htmlPage('Docs',    str_repeat('Documentation text. ', 20)),
    ];

    app()->instance(WebsiteCrawler::class, (new WebsiteCrawler())->withFakes($fakes));

    runCrawlJob($document);

    $document->refresh();

    expect($document->metadata['crawled_urls'])->toBeArray()
        ->and($document->metadata['page_count'])->toBeInt()
        ->and($document->metadata['page_count'])->toBeGreaterThan(0);
});
