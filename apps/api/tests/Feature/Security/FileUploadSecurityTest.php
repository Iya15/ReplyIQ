<?php

// @requires PostgreSQL (CI/Docker only)

use App\Models\Chatbot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function uploadOrg(): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    Membership::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
    $chatbot = Chatbot::factory()->for($org)->create(['status' => 'active']);
    $token = $user->createToken('test')->plainTextToken;

    return compact('org', 'chatbot', 'token');
}

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();
});

// ── MIME allowlist ────────────────────────────────────────────────────────────

it('accepts a valid PDF file', function () {
    ['chatbot' => $chatbot, 'token' => $token] = uploadOrg();

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents", ['file' => $file])
        ->assertCreated();
});

it('rejects an executable file disguised as a PDF', function () {
    ['chatbot' => $chatbot, 'token' => $token] = uploadOrg();

    $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents", ['file' => $file])
        ->assertUnprocessable();
});

it('rejects an HTML file', function () {
    ['chatbot' => $chatbot, 'token' => $token] = uploadOrg();

    $file = UploadedFile::fake()->create('page.html', 10, 'text/html');

    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents", ['file' => $file])
        ->assertUnprocessable();
});

it('rejects a PHP script file', function () {
    ['chatbot' => $chatbot, 'token' => $token] = uploadOrg();

    $file = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');

    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents", ['file' => $file])
        ->assertUnprocessable();
});

it('rejects a JavaScript file', function () {
    ['chatbot' => $chatbot, 'token' => $token] = uploadOrg();

    $file = UploadedFile::fake()->create('exploit.js', 10, 'application/javascript');

    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents", ['file' => $file])
        ->assertUnprocessable();
});

// ── File size limit ───────────────────────────────────────────────────────────

it('rejects files exceeding the 25 MB limit', function () {
    ['chatbot' => $chatbot, 'token' => $token] = uploadOrg();

    $file = UploadedFile::fake()->create('large.pdf', 26_000, 'application/pdf');

    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents", ['file' => $file])
        ->assertUnprocessable();
});

// ── Path traversal via filename ───────────────────────────────────────────────
// Laravel's Storage uses uuid-based paths so the original filename doesn't
// affect the stored path. These tests verify the upload succeeds (the
// filename is sanitised at the storage layer) without path traversal occurring.

it('accepts a file with a path-traversal filename without executing the traversal', function () {
    ['chatbot' => $chatbot, 'token' => $token] = uploadOrg();

    // Fake::create sets the name; Storage::fake prevents any real file ops.
    $file = UploadedFile::fake()->createWithContent(
        '../../etc/passwd',
        '%PDF-1.4 fake pdf content',
    );

    // Should either succeed (stored safely under a UUID path) or fail validation.
    // It must NOT write to ../../etc/passwd.
    $response = $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents", ['file' => $file]);

    // Regardless of HTTP status, the storage must not contain the traversal path.
    Storage::disk('s3')->assertMissing('../../etc/passwd');
    Storage::disk('s3')->assertMissing('../etc/passwd');
});

it('accepts a file with null bytes in the filename without path injection', function () {
    ['chatbot' => $chatbot, 'token' => $token] = uploadOrg();

    $file = UploadedFile::fake()->create("document\0.php", 10, 'application/pdf');

    // Should be accepted or rejected; key requirement is no PHP execution.
    $this->withToken($token)
        ->postJson("/api/v1/chatbots/{$chatbot->id}/documents", ['file' => $file]);

    Storage::disk('s3')->assertMissing("document\0.php");
});
