<?php

namespace App\Services\Knowledge\Extractors;

use App\DataObjects\ExtractedDocument;
use App\Services\Knowledge\Extractors\Contracts\TextExtractor;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;

class DocxExtractor implements TextExtractor
{
    public function supports(string $mimeType): bool
    {
        return $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function extract(string $filePath): ExtractedDocument
    {
        $phpWord = IOFactory::load($filePath);

        $parts = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text = $this->elementToText($element);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        $content = implode("\n\n", $parts);

        return new ExtractedDocument(
            content: $content,
            title: null,
            metadata: ['char_count' => mb_strlen($content)],
        );
    }

    private function elementToText(object $element): string
    {
        if ($element instanceof TextRun) {
            $run = '';
            foreach ($element->getElements() as $child) {
                if ($child instanceof Text) {
                    $run .= $child->getText();
                }
            }

            return $run;
        }

        if ($element instanceof Text) {
            return $element->getText();
        }

        if ($element instanceof AbstractContainer) {
            $parts = [];
            foreach ($element->getElements() as $child) {
                $text = $this->elementToText($child);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }

            return implode("\n", $parts);
        }

        return '';
    }
}
