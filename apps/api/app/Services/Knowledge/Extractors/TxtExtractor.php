<?php

namespace App\Services\Knowledge\Extractors;

use App\DataObjects\ExtractedDocument;
use App\Services\Knowledge\Extractors\Contracts\TextExtractor;
use RuntimeException;

class TxtExtractor implements TextExtractor
{
    public function supports(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'text/');
    }

    public function extract(string $filePath): ExtractedDocument
    {
        $raw = file_get_contents($filePath);

        if ($raw === false) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $content = mb_convert_encoding($raw, 'UTF-8', 'auto');

        return new ExtractedDocument(
            content: $content,
            title: null,
            metadata: ['char_count' => mb_strlen($content)],
        );
    }
}
