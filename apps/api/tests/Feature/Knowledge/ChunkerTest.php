<?php

use App\Services\Knowledge\Chunker;
use App\Services\Knowledge\TextNormalizer;

// ── TextNormalizer ────────────────────────────────────────────────────────────

it('normalizer removes control characters', function () {
    $normalizer = new TextNormalizer;
    $text = "Hello\x00World\x1FTest";

    expect($normalizer->clean($text))->toBe('HelloWorldTest');
});

it('normalizer trims each line', function () {
    $normalizer = new TextNormalizer;

    expect($normalizer->clean("  hello  \n  world  "))->toBe("hello\nworld");
});

it('normalizer collapses 3+ blank lines into 2', function () {
    $normalizer = new TextNormalizer;
    $text = "para one\n\n\n\n\npara two";

    expect($normalizer->clean($text))->toBe("para one\n\npara two");
});

it('normalizer normalizes CRLF line endings', function () {
    $normalizer = new TextNormalizer;

    expect($normalizer->clean("line1\r\nline2\rline3"))->toBe("line1\nline2\nline3");
});

// ── Chunker ───────────────────────────────────────────────────────────────────

it('returns empty array for empty input', function () {
    expect((new Chunker)->chunk(''))->toBe([]);
});

it('returns empty array for whitespace-only input', function () {
    expect((new Chunker)->chunk("   \n\n  "))->toBe([]);
});

it('returns a single chunk when text is under the token limit', function () {
    $text = 'ReplyIQ is an AI chatbot platform for businesses.';
    $chunks = (new Chunker)->chunk($text, 500);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0])->toBe($text);
});

it('produces multiple chunks for a long document', function () {
    $paragraph = str_repeat('word ', 120); // ~156 estimated tokens per paragraph
    $text = implode("\n\n", array_fill(0, 5, trim($paragraph)));

    $chunks = (new Chunker)->chunk($text, 200);

    expect(count($chunks))->toBeGreaterThan(1);
});

it('no chunk is empty', function () {
    $paragraph = str_repeat('word ', 120);
    $text = implode("\n\n", array_fill(0, 5, trim($paragraph)));

    $chunks = (new Chunker)->chunk($text, 200);

    foreach ($chunks as $chunk) {
        expect(trim($chunk))->not->toBe('');
    }
});

it('overlap: last words of chunk N appear at start of chunk N+1', function () {
    // 120 words per paragraph ~ 156 tokens; maxTokens=200 so two paragraphs fill a chunk.
    $paragraph = implode(' ', array_map(fn (int $i) => "word{$i}", range(1, 120)));
    $text = implode("\n\n", [$paragraph, $paragraph, $paragraph]);

    $chunks = (new Chunker)->chunk($text, 200, 50);

    expect(count($chunks))->toBeGreaterThan(1);

    // The overlap tail from chunk 0 should appear somewhere in chunk 1.
    $wordsIn0 = preg_split('/\s+/', trim($chunks[0])) ?: [];
    $overlapWordCount = (int) ceil(50 / 1.3); // ~39 words
    $tailWords = array_slice($wordsIn0, -$overlapWordCount);
    $tailSample = implode(' ', array_slice($tailWords, 0, 5)); // just check first 5 tail words

    expect($chunks[1])->toContain($tailSample);
});

it('keeps a FAQ Q&A pair in the same chunk', function () {
    $faq = "Q: How do I update my chatbot's knowledge base?\nA: Navigate to the Knowledge tab and click Add Document.";
    // Surround with padding so the FAQ is not the only content (tests that chunking doesn't split Q from A)
    $padding = str_repeat('context word ', 50);
    $text = $padding."\n\n".$faq."\n\n".$padding;

    $chunks = (new Chunker)->chunk($text, 500);

    // Find which chunk(s) contain Q: — must be same chunk as A:
    $qChunks = array_filter($chunks, fn ($c) => str_contains($c, 'Q:'));
    $aChunks = array_filter($chunks, fn ($c) => str_contains($c, 'A:'));

    expect($qChunks)->not->toBeEmpty()
        ->and(array_keys($qChunks))->toBe(array_keys($aChunks));
});

it('hard-splits a single paragraph longer than maxTokens', function () {
    // 500 words ~ 650 tokens, maxTokens=100
    $text = implode(' ', array_fill(0, 500, 'longword'));

    $chunks = (new Chunker)->chunk($text, 100);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(trim($chunk))->not->toBe('');
    }
});
