<?php

use App\Services\Knowledge\Extractors\DocxExtractor;
use App\Services\Knowledge\Extractors\ExtractorFactory;
use App\Services\Knowledge\Extractors\PdfExtractor;
use App\Services\Knowledge\Extractors\TxtExtractor;

// ── TXT extractor ─────────────────────────────────────────────────────────────

it('TxtExtractor extracts content from a plain text file', function () {
    $doc = (new TxtExtractor())->extract(base_path('tests/fixtures/sample.txt'));

    expect($doc->content)->toContain('Getting Started')
        ->and($doc->metadata['char_count'])->toBeGreaterThan(100);
});

it('TxtExtractor::supports() matches text/* MIME types', function () {
    $extractor = new TxtExtractor();

    expect($extractor->supports('text/plain'))->toBeTrue()
        ->and($extractor->supports('text/markdown'))->toBeTrue()
        ->and($extractor->supports('application/pdf'))->toBeFalse();
});

// ── PDF extractor ─────────────────────────────────────────────────────────────

it('PdfExtractor extracts text from a PDF file', function () {
    $doc = (new PdfExtractor())->extract(base_path('tests/fixtures/sample.pdf'));

    expect($doc->content)->toContain('Getting Started')
        ->and($doc->metadata['char_count'])->toBeGreaterThan(0)
        ->and($doc->metadata['page_count'])->toBe(1);
});

it('PdfExtractor captures title from PDF metadata', function () {
    $doc = (new PdfExtractor())->extract(base_path('tests/fixtures/sample.pdf'));

    expect($doc->title)->toBe('ReplyIQ Help Center');
});

it('PdfExtractor::supports() matches application/pdf only', function () {
    $extractor = new PdfExtractor();

    expect($extractor->supports('application/pdf'))->toBeTrue()
        ->and($extractor->supports('text/plain'))->toBeFalse();
});

// ── DOCX extractor ────────────────────────────────────────────────────────────

it('DocxExtractor extracts text from a DOCX file', function () {
    $doc = (new DocxExtractor())->extract(base_path('tests/fixtures/sample.docx'));

    expect($doc->content)->toContain('Getting Started')
        ->and($doc->metadata['char_count'])->toBeGreaterThan(0);
});

it('DocxExtractor preserves Q&A content', function () {
    $doc = (new DocxExtractor())->extract(base_path('tests/fixtures/sample.docx'));

    expect($doc->content)->toContain('Q:')
        ->and($doc->content)->toContain('A:');
});

it('DocxExtractor::supports() matches DOCX MIME type only', function () {
    $extractor = new DocxExtractor();
    $docxMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    expect($extractor->supports($docxMime))->toBeTrue()
        ->and($extractor->supports('application/pdf'))->toBeFalse();
});

// ── Factory ───────────────────────────────────────────────────────────────────

it('ExtractorFactory resolves TxtExtractor for text/plain', function () {
    expect(ExtractorFactory::resolve('text/plain'))->toBeInstanceOf(TxtExtractor::class);
});

it('ExtractorFactory resolves PdfExtractor for application/pdf', function () {
    expect(ExtractorFactory::resolve('application/pdf'))->toBeInstanceOf(PdfExtractor::class);
});

it('ExtractorFactory resolves DocxExtractor for the DOCX MIME type', function () {
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    expect(ExtractorFactory::resolve($mime))->toBeInstanceOf(DocxExtractor::class);
});

it('ExtractorFactory throws for an unsupported MIME type', function () {
    expect(fn() => ExtractorFactory::resolve('image/png'))
        ->toThrow(InvalidArgumentException::class);
});
