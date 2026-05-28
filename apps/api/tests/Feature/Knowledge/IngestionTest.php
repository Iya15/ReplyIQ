<?php

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocumentJob;
use App\Models\Chatbot;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Organization;
use App\Repositories\DocumentRepository;
use App\Services\Embedding\EmbeddingClient;
use App\Services\Knowledge\IngestManualTextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function fakeEmbedder(): EmbeddingClient
{
    return new class implements EmbeddingClient
    {
        public function embed(string $text): array
        {
            return array_fill(0, 1536, 0.1);
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn () => array_fill(0, 1536, 0.1), $texts);
        }

        public function dimension(): int
        {
            return 1536;
        }

        public function model(): string
        {
            return 'test-model';
        }
    };
}

function makeOrganizationWithChatbot(): array
{
    $org = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create();
    app()->instance('currentOrganization', $org);

    return [$org, $chatbot];
}

function runJob(Document $document): void
{
    app()->instance(EmbeddingClient::class, fakeEmbedder());
    app()->call([new ProcessDocumentJob($document), 'handle']);
}

// ── PDF ingestion ─────────────────────────────────────────────────────────────

it('ingests a PDF fixture and produces ready document with chunks', function () {
    Storage::fake('s3');
    [$org, $chatbot] = makeOrganizationWithChatbot();

    $storageKey = "documents/{$org->id}/{$chatbot->id}/sample.pdf";
    Storage::disk('s3')->put($storageKey, file_get_contents(base_path('tests/fixtures/sample.pdf')));

    $document = Document::factory()->for($org)->for($chatbot)->create([
        'source_type' => DocumentSourceType::Pdf,
        'source_url' => $storageKey,
        'status' => DocumentStatus::Pending,
    ]);

    runJob($document);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->chunk_count)->toBeGreaterThan(0)
        ->and($document->char_count)->toBeGreaterThan(0)
        ->and($document->processed_at)->not->toBeNull();

    expect(
        Chunk::withoutGlobalScopes()->where('document_id', $document->id)->count()
    )->toBe($document->chunk_count);
});

it('chunks have embeddings and correct metadata model after ingestion', function () {
    Storage::fake('s3');
    [$org, $chatbot] = makeOrganizationWithChatbot();

    $storageKey = "documents/{$org->id}/{$chatbot->id}/sample.txt";
    Storage::disk('s3')->put($storageKey, file_get_contents(base_path('tests/fixtures/sample.txt')));

    $document = Document::factory()->for($org)->for($chatbot)->create([
        'source_type' => DocumentSourceType::Txt,
        'source_url' => $storageKey,
        'status' => DocumentStatus::Pending,
    ]);

    runJob($document);

    $chunk = Chunk::withoutGlobalScopes()->where('document_id', $document->id)->first();

    expect($chunk)->not->toBeNull()
        ->and($chunk->embedding)->toBeArray()->toHaveCount(1536)
        ->and($chunk->metadata['model'])->toBe('test-model');
});

// ── Manual text ingestion ─────────────────────────────────────────────────────

it('ingests manual text and produces ready document with chunks', function () {
    [$org, $chatbot] = makeOrganizationWithChatbot();

    $service = new IngestManualTextService;
    Queue::fake();

    $document = $service->execute(
        chatbot: $chatbot,
        title: 'My FAQ',
        content: 'ReplyIQ is an AI chatbot platform. It supports PDF, DOCX, and TXT uploads.',
    );

    expect($document->status)->toBe(DocumentStatus::Pending)
        ->and($document->source_type)->toBe(DocumentSourceType::Manual)
        ->and($document->metadata['raw_content'])->toContain('ReplyIQ');

    Queue::assertPushed(ProcessDocumentJob::class);

    // Now run job synchronously to verify end-to-end
    Queue::restore();
    runJob($document);
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->chunk_count)->toBeGreaterThan(0);
});

// ── Failure handling ──────────────────────────────────────────────────────────

it('marks document as failed when storage file does not exist', function () {
    Storage::fake('s3');
    [$org, $chatbot] = makeOrganizationWithChatbot();

    $document = Document::factory()->for($org)->for($chatbot)->create([
        'source_type' => DocumentSourceType::Pdf,
        'source_url' => 'documents/nonexistent/file.pdf',
        'status' => DocumentStatus::Pending,
    ]);

    expect(fn () => runJob($document))->toThrow(Throwable::class);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->not->toBeNull();
});

it('marks document as failed when embedding throws', function () {
    Storage::fake('s3');
    [$org, $chatbot] = makeOrganizationWithChatbot();

    $storageKey = "documents/{$org->id}/{$chatbot->id}/sample.txt";
    Storage::disk('s3')->put($storageKey, 'Some content.');

    $document = Document::factory()->for($org)->for($chatbot)->create([
        'source_type' => DocumentSourceType::Txt,
        'source_url' => $storageKey,
        'status' => DocumentStatus::Pending,
    ]);

    $brokenEmbedder = new class implements EmbeddingClient
    {
        public function embed(string $text): array
        {
            throw new RuntimeException('OpenAI down');
        }

        public function embedBatch(array $texts): array
        {
            throw new RuntimeException('OpenAI down');
        }

        public function dimension(): int
        {
            return 1536;
        }

        public function model(): string
        {
            return 'test-model';
        }
    };

    app()->instance(EmbeddingClient::class, $brokenEmbedder);

    expect(fn () => app()->call([new ProcessDocumentJob($document), 'handle']))->toThrow(Throwable::class);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe('OpenAI down');
});

// ── Reprocess ─────────────────────────────────────────────────────────────────

it('reprocess deletes old chunks and dispatches a new job', function () {
    [$org, $chatbot] = makeOrganizationWithChatbot();

    $document = Document::factory()->for($org)->for($chatbot)->create([
        'source_type' => DocumentSourceType::Manual,
        'status' => DocumentStatus::Ready,
        'chunk_count' => 3,
        'metadata' => ['raw_content' => 'Old content.'],
    ]);

    Chunk::factory()->count(3)->for($org)->for($chatbot)->for($document)->create();

    expect(Chunk::withoutGlobalScopes()->where('document_id', $document->id)->count())->toBe(3);

    Queue::fake();

    (new DocumentRepository)->reprocess($document);

    expect(Chunk::withoutGlobalScopes()->where('document_id', $document->id)->count())->toBe(0);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Pending)
        ->and($document->chunk_count)->toBe(0);

    Queue::assertPushed(ProcessDocumentJob::class);
});

it('reprocess job clears any stale chunks that survived between dispatch and run', function () {
    [$org, $chatbot] = makeOrganizationWithChatbot();

    $document = Document::factory()->for($org)->for($chatbot)->create([
        'source_type' => DocumentSourceType::Manual,
        'status' => DocumentStatus::Pending,
        'metadata' => ['raw_content' => 'Fresh content about ReplyIQ chatbots.'],
    ]);

    // Simulate a stale chunk left by a previous failed attempt.
    Chunk::factory()->for($org)->for($chatbot)->for($document)->create();

    expect(Chunk::withoutGlobalScopes()->where('document_id', $document->id)->count())->toBe(1);

    runJob($document);
    $document->refresh();

    // The job's idempotency DELETE removed the stale chunk; only new chunks remain.
    expect(Chunk::withoutGlobalScopes()->where('document_id', $document->id)->count())
        ->toBe($document->chunk_count);
});
