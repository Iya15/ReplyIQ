<?php

namespace App\Services\Knowledge;

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocumentJob;
use App\Models\Chatbot;
use App\Models\Document;

class IngestManualTextService
{
    public function execute(Chatbot $chatbot, string $title, string $content): Document
    {
        $document = Document::create([
            'organization_id' => $chatbot->organization_id,
            'chatbot_id' => $chatbot->id,
            'source_type' => DocumentSourceType::Manual,
            'source_url' => null,
            'title' => $title,
            'status' => DocumentStatus::Pending,
            'metadata' => ['raw_content' => $content],
        ]);

        ProcessDocumentJob::dispatch($document);

        return $document;
    }
}
