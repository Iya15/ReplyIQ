<?php

namespace App\Repositories;

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Jobs\CrawlWebsiteJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Chatbot;
use App\Models\Document;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DocumentRepository
{
    /**
     * @param  array{status?: string}  $filters
     */
    public function paginate(Chatbot $chatbot, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Document::query()
            ->where('chatbot_id', $chatbot->id)
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('status', $filters['status']),
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Chatbot $chatbot, array $data): Document
    {
        return $chatbot->documents()->create($data);
    }

    public function find(string $id): ?Document
    {
        return Document::find($id);
    }

    public function delete(Document $document): void
    {
        DB::transaction(function () use ($document): void {
            DB::table('chunks')->where('document_id', $document->id)->delete();
            $document->delete();
        });
    }

    /**
     * Reset the document to pending, clear its chunks, and re-dispatch ingestion.
     */
    public function reprocess(Document $document): void
    {
        DB::transaction(function () use ($document): void {
            DB::table('chunks')->where('document_id', $document->id)->delete();

            $document->update([
                'status' => DocumentStatus::Pending,
                'chunk_count' => 0,
                'char_count' => null,
                'processed_at' => null,
                'error_message' => null,
            ]);
        });

        if ($document->source_type === DocumentSourceType::Url) {
            CrawlWebsiteJob::dispatch($document);
        } else {
            ProcessDocumentJob::dispatch($document);
        }
    }
}
