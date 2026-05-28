<?php

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocumentJob;
use App\Models\Chatbot;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeDocOwner(): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
    $token = $user->createToken('t')->plainTextToken;

    return [$org, $user, $token];
}

function makeDocMember(Organization $org): array
{
    $user = User::factory()->create();
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'member']);

    return [$user, $user->createToken('t')->plainTextToken];
}

// ── Index ─────────────────────────────────────────────────────────────────────

it('lists documents for a chatbot', function () {
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();
    Document::factory()->count(3)->for($org)->for($chatbot)->create();

    $this->withToken($token)
        ->getJson("/api/v1/chatbots/{$chatbot->id}/documents")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('filters documents by status', function () {
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();
    Document::factory()->for($org)->for($chatbot)->create(['status' => DocumentStatus::Ready]);
    Document::factory()->for($org)->for($chatbot)->create(['status' => DocumentStatus::Failed]);

    $this->withToken($token)
        ->getJson("/api/v1/chatbots/{$chatbot->id}/documents?status=ready")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'ready');
});

it('returns empty list when chatbot has no documents', function () {
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->getJson("/api/v1/chatbots/{$chatbot->id}/documents")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ── Store file ────────────────────────────────────────────────────────────────

it('uploads a PDF and returns 202 with pending document', function () {
    Storage::fake('s3');
    Queue::fake();
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->post(
            "/api/v1/chatbots/{$chatbot->id}/documents",
            ['file' => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf')],
            ['Accept' => 'application/json'],
        )
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.source_type', 'pdf');
});

it('uses the optional title override when supplied', function () {
    Storage::fake('s3');
    Queue::fake();
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->post(
            "/api/v1/chatbots/{$chatbot->id}/documents",
            [
                'file' => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
                'title' => 'Custom Title',
            ],
            ['Accept' => 'application/json'],
        )
        ->assertStatus(202)
        ->assertJsonPath('data.title', 'Custom Title');
});

it('rejects a file exceeding 25 MB', function () {
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->post(
            "/api/v1/chatbots/{$chatbot->id}/documents",
            ['file' => UploadedFile::fake()->create('huge.pdf', 26624, 'application/pdf')],
            ['Accept' => 'application/json'],
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('file');
});

it('rejects an unsupported file type', function () {
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->post(
            "/api/v1/chatbots/{$chatbot->id}/documents",
            ['file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')],
            ['Accept' => 'application/json'],
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('file');
});

it('dispatches ProcessDocumentJob after a successful file upload', function () {
    Storage::fake('s3');
    Queue::fake();
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->post(
            "/api/v1/chatbots/{$chatbot->id}/documents",
            ['file' => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf')],
            ['Accept' => 'application/json'],
        )
        ->assertStatus(202);

    Queue::assertPushed(ProcessDocumentJob::class);
});

// ── Store text ────────────────────────────────────────────────────────────────

it('creates a manual text document and returns 202', function () {
    Queue::fake();
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents/text", [
            'title' => 'Help Article',
            'content' => 'ReplyIQ is a chatbot platform.',
        ])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.source_type', 'manual')
        ->assertJsonPath('data.title', 'Help Article');
});

it('rejects text document with missing title', function () {
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents/text", [
            'content' => 'Some content.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('title');
});

it('rejects text document with missing content', function () {
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents/text", [
            'title' => 'FAQ',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('content');
});

// ── Destroy ───────────────────────────────────────────────────────────────────

it('deletes a document and cascades to its chunks', function () {
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();
    $document = Document::factory()->for($org)->for($chatbot)->create();
    Chunk::factory()->count(3)->for($org)->for($chatbot)->for($document)->create();

    expect(Chunk::withoutGlobalScopes()->where('document_id', $document->id)->count())->toBe(3);

    $this->withToken($token)
        ->deleteJson("/api/v1/documents/{$document->id}")
        ->assertOk();

    expect(Document::withoutGlobalScopes()->find($document->id))->toBeNull();
    expect(Chunk::withoutGlobalScopes()->where('document_id', $document->id)->count())->toBe(0);
});

// ── Reprocess ─────────────────────────────────────────────────────────────────

it('reprocess resets document to pending and dispatches job', function () {
    Queue::fake();
    [$org, , $token] = makeDocOwner();
    $chatbot = Chatbot::factory()->for($org)->create();
    $document = Document::factory()->for($org)->for($chatbot)->create([
        'status' => DocumentStatus::Failed,
        'error_message' => 'Something went wrong.',
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/documents/{$document->id}/reprocess")
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending');

    Queue::assertPushed(ProcessDocumentJob::class);
});

// ── Cross-tenant isolation ────────────────────────────────────────────────────

it('returns 404 when accessing another org chatbot documents', function () {
    [$_orgA, $_userA, $token] = makeDocOwner();
    [$orgB] = makeDocOwner();
    $chatbotB = Chatbot::factory()->for($orgB)->create();

    $this->withToken($token)
        ->getJson("/api/v1/chatbots/{$chatbotB->id}/documents")
        ->assertNotFound();
});

it('returns 404 when deleting a document belonging to another org', function () {
    [$_orgA, $_userA, $token] = makeDocOwner();
    [$orgB] = makeDocOwner();
    $chatbotB = Chatbot::factory()->for($orgB)->create();
    $docB = Document::factory()->for($orgB)->for($chatbotB)->create();

    $this->withToken($token)
        ->deleteJson("/api/v1/documents/{$docB->id}")
        ->assertNotFound();
});

it('returns 404 when reprocessing a document belonging to another org', function () {
    [$_orgA, $_userA, $token] = makeDocOwner();
    [$orgB] = makeDocOwner();
    $chatbotB = Chatbot::factory()->for($orgB)->create();
    $docB = Document::factory()->for($orgB)->for($chatbotB)->create();

    $this->withToken($token)
        ->postJson("/api/v1/documents/{$docB->id}/reprocess")
        ->assertNotFound();
});

// ── Unauthenticated ───────────────────────────────────────────────────────────

it('rejects unauthenticated requests', function () {
    $chatbotId = 'fake-id';

    $this->getJson("/api/v1/chatbots/{$chatbotId}/documents")
        ->assertUnauthorized();
});
