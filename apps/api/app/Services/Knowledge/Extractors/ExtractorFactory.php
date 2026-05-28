<?php

namespace App\Services\Knowledge\Extractors;

use App\Services\Knowledge\Extractors\Contracts\TextExtractor;
use InvalidArgumentException;

class ExtractorFactory
{
    /** @var TextExtractor[] */
    private static array $extractors = [];

    public static function resolve(string $mimeType): TextExtractor
    {
        foreach (self::extractors() as $extractor) {
            if ($extractor->supports($mimeType)) {
                return $extractor;
            }
        }

        throw new InvalidArgumentException("No extractor supports MIME type: {$mimeType}");
    }

    /** @return TextExtractor[] */
    private static function extractors(): array
    {
        if (self::$extractors === []) {
            self::$extractors = [
                new PdfExtractor,
                new DocxExtractor,
                new TxtExtractor,
            ];
        }

        return self::$extractors;
    }
}
