<?php

use App\Models\Chatbot;
use App\Models\ChatbotSettings;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// Disposable test route: mirrors what real chatbot routes will look like.
// Route model binding + TenantScope = org-scoped 404 without explicit policy.
beforeEach(function () {
    Route::middleware(['auth:sanctum', 'tenant'])
        ->get('/_test/chatbots/{chatbot}', fn (Chatbot $chatbot) => response()->json(['id' => $chatbot->id]));
});

function makeUserWithOrg(): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
    $token = $user->createToken('test')->plainTextToken;

    return [$org, $user, $token];
}

// ── Cross-tenant isolation ────────────────────────────────────────────────────

it('returns 404 (not 403) when user A tries to access a chatbot owned by org B', function () {
    [, , $tokenA] = makeUserWithOrg();
    [$orgB] = makeUserWithOrg();

    // Create a chatbot belonging to org B.
    $chatbotB = Chatbot::factory()->for($orgB)->create();

    // TenantScope makes org B's chatbot invisible to org A's query,
    // so route model binding returns 404 instead of leaking a 403.
    $this->withToken($tokenA)
        ->getJson("/_test/chatbots/{$chatbotB->id}")
        ->assertNotFound();
});

// ── organization_id auto-fill ─────────────────────────────────────────────────

it('auto-fills organization_id from the bound tenant when creating a chatbot', function () {
    [$org] = makeUserWithOrg();

    // Simulate what ResolveTenant middleware does on every authenticated request.
    app()->instance('currentOrganization', $org);

    $chatbot = Chatbot::create(['name' => 'Test Bot']);

    expect($chatbot->organization_id)->toBe($org->id);
});

// ── ChatbotSettings auto-created via observer ─────────────────────────────────

it('auto-creates ChatbotSettings with correct defaults when a chatbot is created', function () {
    $chatbot = Chatbot::factory()->create();

    expect($chatbot->settings)->toBeInstanceOf(ChatbotSettings::class);
    expect($chatbot->settings->primary_color)->toBe('#4F46E5');
    expect($chatbot->settings->text_color)->toBe('#0F172A');
    expect($chatbot->settings->welcome_message)->toBe('Hi! How can I help you today?');
    expect($chatbot->settings->model)->toBe('gpt-4o-mini');
    expect($chatbot->settings->temperature)->toBe(0.3);
    expect($chatbot->settings->show_branding)->toBeTrue();
    expect($chatbot->settings->allowed_domains)->toBe([]);
});
