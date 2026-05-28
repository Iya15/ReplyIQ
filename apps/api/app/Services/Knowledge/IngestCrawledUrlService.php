<?php

namespace App\Services\Knowledge;

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Jobs\CrawlWebsiteJob;
use App\Models\Chatbot;
use App\Models\Document;

class IngestCrawledUrlService
{
    public function execute(Chatbot $chatbot, string $url, int $maxPages = 50): Document
    {
        $document = Document::create([
            'organization_id' => $chatbot->organization_id,
            'chatbot_id' => $chatbot->id,
            'source_type' => DocumentSourceType::Url,
            'source_url' => $url,
            'title' => parse_url($url, PHP_URL_HOST) ?? $url,
            'status' => DocumentStatus::Pending,
            'metadata' => ['max_pages' => $maxPages],
        ]);

        CrawlWebsiteJob::dispatch($document);

        return $document;
    }
}
