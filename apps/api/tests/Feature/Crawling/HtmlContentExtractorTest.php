<?php

use App\Services\Crawling\HtmlContentExtractor;

function extractor(): HtmlContentExtractor
{
    return new HtmlContentExtractor();
}

// ── extract ───────────────────────────────────────────────────────────────────

it('returns text from a simple HTML page', function () {
    $html = '<html><body><p>Hello world.</p></body></html>';
    expect(extractor()->extract($html))->toContain('Hello world.');
});

it('strips script and style tags', function () {
    $html = '<html><body>'
        . '<script>alert("xss")</script>'
        . '<style>.hidden { display: none; }</style>'
        . '<p>Real content here.</p>'
        . '</body></html>';

    $text = extractor()->extract($html);

    expect($text)
        ->not->toContain('alert')
        ->not->toContain('.hidden')
        ->toContain('Real content here.');
});

it('strips nav, header, footer, and aside elements', function () {
    $html = '<html><body>'
        . '<header>Site Header</header>'
        . '<nav>Menu links</nav>'
        . '<main>Main article content.</main>'
        . '<aside>Sidebar ad</aside>'
        . '<footer>Copyright 2025</footer>'
        . '</body></html>';

    $text = extractor()->extract($html);

    expect($text)
        ->toContain('Main article content.')
        ->not->toContain('Site Header')
        ->not->toContain('Menu links')
        ->not->toContain('Sidebar ad')
        ->not->toContain('Copyright 2025');
});

it('prefers <main> content over the full body', function () {
    $html = '<html><body>'
        . '<div>Body noise</div>'
        . '<main>Primary content lives here.</main>'
        . '</body></html>';

    $text = extractor()->extract($html);

    expect($text)
        ->toContain('Primary content lives here.')
        ->not->toContain('Body noise');
});

it('falls back to <article> when there is no <main>', function () {
    $html = '<html><body>'
        . '<div>Body noise</div>'
        . '<article>Article text here.</article>'
        . '</body></html>';

    $text = extractor()->extract($html);

    expect($text)->toContain('Article text here.');
});

it('falls back to <body> when neither <main> nor <article> exists', function () {
    $html = '<html><body><p>Only body content.</p></body></html>';
    expect(extractor()->extract($html))->toContain('Only body content.');
});

it('strips elements with role=navigation', function () {
    $html = '<html><body>'
        . '<div role="navigation">Nav menu</div>'
        . '<main>Actual content.</main>'
        . '</body></html>';

    $text = extractor()->extract($html);

    expect($text)
        ->not->toContain('Nav menu')
        ->toContain('Actual content.');
});

it('handles malformed HTML without throwing', function () {
    $html = '<html><body><p>Unclosed paragraph<div>Some <b>bold text</body>';
    expect(fn () => extractor()->extract($html))->not->toThrow(\Throwable::class);
});

// ── extractTitle ──────────────────────────────────────────────────────────────

it('extracts the <title> tag', function () {
    $html = '<html><head><title>My Page Title</title></head><body>...</body></html>';
    expect(extractor()->extractTitle($html))->toBe('My Page Title');
});

it('falls back to the first <h1> when <title> is absent', function () {
    $html = '<html><body><h1>Page Heading</h1><p>Content.</p></body></html>';
    expect(extractor()->extractTitle($html))->toBe('Page Heading');
});

it('returns an empty string when neither <title> nor <h1> is present', function () {
    $html = '<html><body><p>No heading here.</p></body></html>';
    expect(extractor()->extractTitle($html))->toBe('');
});
