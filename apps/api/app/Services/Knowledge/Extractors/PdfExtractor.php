<?php

namespace App\Services\Knowledge\Extractors;

use App\DataObjects\ExtractedDocument;
use App\Services\Knowledge\Extractors\Contracts\TextExtractor;
use Smalot\PdfParser\Parser;

class PdfExtractor implements TextExtractor
{
    public function supports(string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    public function extract(string $filePath): ExtractedDocument
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);

        $details = $pdf->getDetails();
        $title = isset($details['Title']) && $details['Title'] !== ''
            ? (string) $details['Title']
            : null;

        $pages = $pdf->getPages();
        $parts = [];

        foreach ($pages as $page) {
            $pageText = trim($page->getText());
            if ($pageText !== '') {
                $parts[] = $pageText;
            }
        }

        $content = implode("\n\n", $parts);

        return new ExtractedDocument(
            content: $content,
            title: $title,
            metadata: [
                'char_count' => mb_strlen($content),
                'page_count' => count($pages),
            ],
        );
    }
}
