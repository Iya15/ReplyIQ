<?php

use App\DataObjects\RetrievedChunk;
use App\Models\Chatbot;
use App\Models\Organization;
use App\Services\Ai\PromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create an org + chatbot with deterministic settings, return both plus a fresh PromptBuilder.
 *
 * @return array{0: Chatbot, 1: PromptBuilder}
 */
function makeBuilderChatbot(array $settingsOverrides = []): array
{
    $org = Organization::factory()->create(['name' => 'Acme Corp']);
    $chatbot = Chatbot::factory()->for($org)->create(['name' => 'SupportBot']);

    // ChatbotObserver creates the settings row automatically; we just update it.
    $chatbot->settings->update(array_merge([
        'ai_tone' => 'professional and concise',
        'ai_persona' => 'A friendly support specialist',
        'fallback_message' => 'I cannot answer that question right now.',
    ], $settingsOverrides));

    $chatbot->load(['settings', 'organization']);

    return [$chatbot, new PromptBuilder];
}

/**
 * @return Collection<int, RetrievedChunk>
 */
function makeChunkCollection(string ...$contents): Collection
{
    return collect($contents)->values()->map(fn (string $c, int $i) => new RetrievedChunk(
        id: (string) $i,
        content: $c,
        similarity: 0.9,
        document_id: 'doc-'.$i,
        metadata: [],
    ));
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('puts the system message first', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    $messages = $builder->build($chatbot, 'Hello?', collect());

    expect($messages[0]['role'])->toBe('system');
});

it('includes chatbot name in the system message', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    $system = $builder->build($chatbot, 'Hello?', collect())[0]['content'];

    expect($system)->toContain('SupportBot');
});

it('includes organization name in the system message', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    $system = $builder->build($chatbot, 'Hello?', collect())[0]['content'];

    expect($system)->toContain('Acme Corp');
});

it('includes ai_tone and ai_persona in the system message', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    $system = $builder->build($chatbot, 'Hello?', collect())[0]['content'];

    expect($system)
        ->toContain('professional and concise')
        ->toContain('A friendly support specialist');
});

it('omits the persona line when ai_persona is null', function () {
    [$chatbot, $builder] = makeBuilderChatbot(['ai_persona' => null]);

    $system = $builder->build($chatbot, 'Hello?', collect())[0]['content'];

    expect($system)->not->toContain('Persona:');
});

it('includes the fallback message in the system prompt', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    $system = $builder->build($chatbot, 'Hello?', collect())[0]['content'];

    expect($system)->toContain('I cannot answer that question right now.');
});

it('joins retrieved chunks into the system message', function () {
    [$chatbot, $builder] = makeBuilderChatbot();
    $chunks = makeChunkCollection('How to reset a password.', 'Contact support at help@acme.com.');

    $system = $builder->build($chatbot, 'reset password', $chunks)[0]['content'];

    expect($system)
        ->toContain('How to reset a password.')
        ->toContain('Contact support at help@acme.com.');
});

it('uses the no-context marker when chunks collection is empty', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    $system = $builder->build($chatbot, 'anything', collect())[0]['content'];

    expect($system)->toContain('[no relevant context found]');
});

it('wraps the current query in <<<USER>>> ... <<<END>>> delimiters', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    $messages = $builder->build($chatbot, 'What are your hours?', collect());
    $last = end($messages);

    expect($last['role'])->toBe('user')
        ->and($last['content'])->toContain("<<<USER>>>\nWhat are your hours?\n<<<END>>>");
});

it('places history turns between system and current user message', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    $history = [
        ['role' => 'user',      'content' => 'Prior question'],
        ['role' => 'assistant', 'content' => 'Prior answer'],
    ];

    $messages = $builder->build($chatbot, 'Follow-up?', collect(), $history);

    expect($messages)->toHaveCount(4) // system + 2 history + 1 user
        ->and($messages[1])->toBe(['role' => 'user',      'content' => 'Prior question'])
        ->and($messages[2])->toBe(['role' => 'assistant', 'content' => 'Prior answer'])
        ->and($messages[3]['role'])->toBe('user');
});

it('trims history to the last 6 messages', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    // 10 turns; only the last 6 should survive.
    $history = [];
    for ($i = 1; $i <= 10; $i++) {
        $history[] = ['role' => $i % 2 === 1 ? 'user' : 'assistant', 'content' => "Turn {$i}"];
    }

    $messages = $builder->build($chatbot, 'Current question', collect(), $history);

    // system + 6 history + 1 current = 8
    expect($messages)->toHaveCount(8)
        ->and($messages[1]['content'])->toBe('Turn 5')   // oldest surviving turn
        ->and($messages[6]['content'])->toBe('Turn 10'); // newest history turn
});

it('builds with no history and no chunks correctly', function () {
    [$chatbot, $builder] = makeBuilderChatbot();

    $messages = $builder->build($chatbot, 'Hi there!', collect());

    // system + current user only
    expect($messages)->toHaveCount(2)
        ->and($messages[0]['role'])->toBe('system')
        ->and($messages[1]['role'])->toBe('user');
});
