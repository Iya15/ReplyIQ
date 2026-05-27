<?php

namespace App\Services\Knowledge;

class TextNormalizer
{
    public function clean(string $text): string
    {
        // Remove control characters except \n, \r, \t
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Trim each line
        $lines = array_map('trim', explode("\n", $text));
        $text = implode("\n", $lines);

        // Collapse 3+ consecutive newlines into 2
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        // Collapse multiple spaces within a line
        $text = (string) preg_replace('/ {2,}/', ' ', $text);

        return trim($text);
    }
}
