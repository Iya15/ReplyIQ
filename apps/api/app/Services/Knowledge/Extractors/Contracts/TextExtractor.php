<?php

namespace App\Services\Knowledge\Extractors\Contracts;

use App\DataObjects\ExtractedDocument;

interface TextExtractor
{
    public function supports(string $mimeType): bool;

    public function extract(string $filePath): ExtractedDocument;
}
